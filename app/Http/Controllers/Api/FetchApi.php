<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;
use App\Models\Yasuser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class FetchApi extends Controller
{
    private const CACHE_DURATION = 60;
    public function __construct(
        private ReferralService $referralService
    ) {
    }

    public function fetch_api(Request $request)
    {
        $validated = $request->validate([
            'refercode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
        ]);

        try {
            $refercode = $validated['refercode'];

            $result = $this->referralService
                ->fetchAndSync($refercode);

            // Referral data changed, therefore ranking cache
            // must be invalidated.
            if ($result['hasChanges']) {
                Cache::forget('yasuser_ranking_all');
            }

            return response()->json([
                'status' => 200,
                'message' => $result['hasChanges']
                    ? 'User data updated'
                    : 'User data synchronized',
                'data' => $result['user'],
                'hasChanges' => $result['hasChanges'],
                'changes' => $result['changes'],
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to synchronize referral data.',
            ], 500);
        }
    }
    public function ranking_api()
        {   
            $cacheKey = 'yasuser_ranking_all';

            $cachedRanking = Cache::get($cacheKey);

            if ($cachedRanking !== null) {

                Log::info('Cache hit for ranking');

                return response()->json([
                    'status' => 200,
                    'message' => 'Rankings retrieved from cache',
                    'cached' => true,
                    'data' => $cachedRanking,
                ]);
            }

            $competitors = Yasuser::query()
                ->orderByDesc('total_inviter_number')
                ->get()
                ->map(function ($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'id' => $user->id,
                        'refercode' => $user->refercode,
                        'name' => $user->compitetor_name,
                        'total_inviter_number' => $user->total_inviter_number,
                    ];
                })
                ->values()
                ->all();

            Cache::put(
                $cacheKey,
                $competitors,
                self::CACHE_DURATION
            );

            Log::info('Successfully retrieved rankings');

            return response()->json([
                'status' => 200,
                'message' => 'Rankings retrieved successfully',
                'cached' => false,
                'data' => $competitors,
            ]);
        }}