<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yasuser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class FetchApi extends Controller
{
    /**
     * Cache duration in minutes
     */
    const CACHE_DURATION = 60;
    
    /**
     * External API base URL
     */
    protected $externalApiUrl = 'http://127.0.0.1:8001/api/yas/user/';
    
    /**
     * HTTP request timeout in seconds
     */
    const API_TIMEOUT = 30;

    /**
     * Fetch user data from external API and sync with database
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetch_api(Request $request)
    {
        $validated = null; // Initialize to avoid undefined variable
        
        try {
            // Validate input
            $validated = $request->validate([
                'refercode' => 'required|string|max:255|regex:/^[a-zA-Z0-9_-]+$/'
            ]);

            $refercode = $validated['refercode'];

            // Check cache first
            $cacheKey = "yasuser_data_{$refercode}";
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                Log::info("Cache hit for refercode: {$refercode}");
                return response()->json([
                    'status' => 200,
                    'message' => 'Data retrieved from cache',
                    'cached' => true,
                    'data' => $cachedData
                ]);
            }

            // Fetch from external API
            $externalData = $this->fetchFromExternalApi($refercode);

            if (!$externalData) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found in external API'
                ], 404);
            }

            // Sync data to database and check if data changed
            $result = $this->syncUserData($refercode, $externalData);
            $syncedUser = $result['user'];
            $hasChanges = $result['hasChanges'];
            $changes = $result['changes'] ?? [];

            // Cache the data
            Cache::put($cacheKey, $syncedUser, self::CACHE_DURATION);

            if ($hasChanges) {
                Log::info("Data updated for refercode: {$refercode}", [
                    'changes' => $changes
                ]);
            } else {
                Log::info("No changes detected for refercode: {$refercode}");
            }

            return response()->json([
                'status' => 200,
                'message' => $hasChanges ? 'User data updated' : 'No changes detected',
                'cached' => false,
                'data' => $syncedUser,
                'hasChanges' => $hasChanges,
                'changes' => $changes
            ]);

        } catch (ValidationException $e) {
            Log::warning("Validation error in fetch_api", ['errors' => $e->errors()]);
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error("Error in fetch_api", [
                'refercode' => $validated['refercode'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * Fetch user data from external API with error handling
     * 
     * @param string $refercode
     * @return array|null
     * @throws Exception
     */
    private function fetchFromExternalApi(string $refercode)
    {
        try {
            $response = Http::timeout(self::API_TIMEOUT)
                ->retry(3, 100) // Retry up to 3 times with 100ms delay
                ->connectTimeout(10)
                ->post($this->externalApiUrl . urlencode($refercode));

            // Log the full response for debugging
            Log::info("External API response", [
                'refercode' => $refercode,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            // Check if response is successful
            if (!$response->successful()) {
                Log::error("External API request failed", [
                    'refercode' => $refercode,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new Exception("External API returned status {$response->status()}");
            }

            $data = $response->json();

            // Check if data is null or empty
            if ($data === null) {
                Log::error("External API returned null", [
                    'refercode' => $refercode,
                    'body' => $response->body()
                ]);
                throw new Exception("External API returned null response");
            }

            // Validate response structure
            if (!isset($data['customer_all'])) {
                Log::error("Invalid response structure from external API", [
                    'refercode' => $refercode,
                    'response' => $data,
                    'available_keys' => array_keys((array)$data)
                ]);
                throw new Exception("Invalid API response structure. Expected 'customer_all' key. Got keys: " . implode(', ', array_keys((array)$data)));
            }

            return $data['customer_all'];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Connection error with external API", [
                'refercode' => $refercode,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Unable to connect to external API: " . $e->getMessage());

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("Request error with external API", [
                'refercode' => $refercode,
                'error' => $e->getMessage()
            ]);
            throw new Exception("External API request failed: " . $e->getMessage());
        }
    }

    /**
     * Sync user data to database and detect changes
     * 
     * @param string $refercode
     * @param array $externalData
     * @return array ['user' => Yasuser, 'hasChanges' => bool, 'changes' => array]
     */
    private function syncUserData(string $refercode, array $externalData): array
    {
        // Get existing user data
        $existingUser = Yasuser::where('refercode', $refercode)->first();
        
        $newData = [
            'refercode' => $externalData['refer_code'] ?? null,
            'compitetor_name' => $externalData['customer_name'] ?? null,
            'total_inviter_number' => $externalData['invitor_number'] ?? null,
            'last_synced_at' => now()
        ];

        $changes = [];
        $hasChanges = false;

        // Compare old data with new data
        if ($existingUser) {
            if ($existingUser->compitetor_name !== $newData['compitetor_name']) {
                $changes['compitetor_name'] = [
                    'old' => $existingUser->compitetor_name,
                    'new' => $newData['compitetor_name']
                ];
                $hasChanges = true;
            }

            if ($existingUser->total_inviter_number !== $newData['total_inviter_number']) {
                $changes['total_inviter_number'] = [
                    'old' => $existingUser->total_inviter_number,
                    'new' => $newData['total_inviter_number']
                ];
                $hasChanges = true;
            }

            if ($existingUser->refercode !== $newData['refercode']) {
                $changes['refercode'] = [
                    'old' => $existingUser->refercode,
                    'new' => $newData['refercode']
                ];
                $hasChanges = true;
            }
        } else {
            // New user - mark as changed
            $hasChanges = true;
            $changes['status'] = 'new_user_created';
        }

        // Update or create the user
        $user = Yasuser::updateOrCreate(
            ['refercode' => $refercode],
            $newData
        );

        return [
            'user' => $user,
            'hasChanges' => $hasChanges,
            'changes' => $changes
        ];
    }

    /**
     * Get all users ranked by total_inviter_number
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function ranking_api()
    {
        try {
            $cacheKey = 'yasuser_ranking_all';

            // Check cache first
            $cachedRanking = Cache::get($cacheKey);

            if ($cachedRanking) {
                Log::info("Cache hit for ranking");
                return response()->json([
                    'status' => 200,
                    'message' => 'Rankings retrieved from cache',
                    'cached' => true,
                    'data' => $cachedRanking
                ]);
            }

            // Fetch from database
            $competitors = Yasuser::orderBy('total_inviter_number', 'asc')
                ->get();

            if ($competitors->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'No competitors found'
                ], 404);
            }

            // Cache the ranking
            Cache::put($cacheKey, $competitors, self::CACHE_DURATION);

            Log::info("Successfully retrieved rankings");

            return response()->json([
                'status' => 200,
                'message' => 'Rankings retrieved successfully',
                'cached' => false,
                'data' => $competitors
            ]);

        } catch (Exception $e) {
            Log::error("Error in ranking_api", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while retrieving rankings'
            ], 500);
        }
    }

    /**
     * Clear cache for a specific refercode or all cache
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(Request $request)
    {
        try {
            $refercode = $request->query('refercode');

            if ($refercode) {
                $cacheKey = "yasuser_data_{$refercode}";
                Cache::forget($cacheKey);
                Log::info("Cache cleared for refercode: {$refercode}");

                return response()->json([
                    'status' => 200,
                    'message' => "Cache cleared for refercode: {$refercode}"
                ]);
            }

            // Clear all related caches
            Cache::forget('yasuser_ranking_all');
            Log::info("All caches cleared");

            return response()->json([
                'status' => 200,
                'message' => 'All caches cleared'
            ]);

        } catch (Exception $e) {
            Log::error("Error clearing cache", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Error clearing cache'
            ], 500);
        }
    }
}