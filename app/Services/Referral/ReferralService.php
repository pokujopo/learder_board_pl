<?php

namespace App\Services\Referral;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\Game;
use App\Models\Yasuser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    private const API_TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;

    public function fetchAndSync(
        string $refercode,
        Game $game
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Each game has its own external API
        |--------------------------------------------------------------------------
        */

        $externalData = $this->fetchFromExternalApi(
            $refercode,
            $game
        );

        return $this->syncUser(
            $refercode,
            $externalData,
            $game
        );
    }

    /**
     * Fetch refercode from the external API configured for this game.
     */
    private function fetchFromExternalApi(
        string $refercode,
        Game $game
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Validate game external API configuration
        |--------------------------------------------------------------------------
        */

        $baseUrl = $game->external_api_base_url;

        if (empty($baseUrl)) {

            Log::critical('Game external API URL is not configured', [
                'game_id' => $game->id,
                'game_code' => $game->code,
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Build URL
        |--------------------------------------------------------------------------
        */

        $url = rtrim($baseUrl, '/') . '/' . urlencode($refercode);

        try {

            $response = Http::timeout(
                self::API_TIMEOUT
            )
            ->connectTimeout(
                self::CONNECT_TIMEOUT
            )
            ->retry(
                3,
                100,
                throw: false
            )
            ->post($url);

        } catch (ConnectionException $e) {

            /*
            |--------------------------------------------------------------------------
            | External API cannot be reached
            |--------------------------------------------------------------------------
            */

            Log::error('Referral external API connection failed', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'url' => $url,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 404 = Refercode does not exist
        |--------------------------------------------------------------------------
        */

        if ($response->notFound()) {

            Log::info('Refercode not found in external API', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
            ]);

            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 500, 502, 503, 504 = External API unavailable
        |--------------------------------------------------------------------------
        */

        if ($response->serverError()) {

            Log::error('Referral external API server error', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'url' => $url,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Other 4xx errors
        |--------------------------------------------------------------------------
        */

        if ($response->clientError()) {

            Log::warning('Referral external API client error', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new RefercodeNotFoundException(
                'Refercode could not be verified.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Unexpected HTTP response
        |--------------------------------------------------------------------------
        */

        if (!$response->successful()) {

            Log::error('Unexpected referral API response', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Parse JSON
        |--------------------------------------------------------------------------
        */

        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | Validate response structure
        |--------------------------------------------------------------------------
        */

        if (
            !is_array($data) ||
            !isset($data['customer_all']) ||
            !is_array($data['customer_all'])
        ) {

            Log::error('Invalid referral API response structure', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'response' => $data,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        return $data['customer_all'];
    }

    /**
     * Sync external user into local database.
     */
    private function syncUser(
        string $refercode,
        array $externalData,
        Game $game
    ): array {

        $externalRefercode =
            $externalData['refer_code'] ?? $refercode;

        $newData = [
            'game_id' => $game->id,

            'refercode' =>
                $externalRefercode,

            'compitetor_name' =>
                $externalData['customer_name'] ?? null,

            'total_inviter_number' =>
                (int) (
                    $externalData['invitor_number'] ?? 0
                ),

            'last_synced_at' => now(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Find existing Yasuser for THIS GAME only
        |--------------------------------------------------------------------------
        */

        $existingUser = Yasuser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $externalRefercode)
            ->first();

        $changes = [];

        if ($existingUser) {

            foreach ([
                'compitetor_name',
                'total_inviter_number',
            ] as $field) {

                if (
                    $existingUser->{$field}
                    !=
                    $newData[$field]
                ) {

                    $changes[$field] = [
                        'old' => $existingUser->{$field},
                        'new' => $newData[$field],
                    ];
                }
            }

        } else {

            $changes['status'] = 'new_user_created';
        }

        /*
        |--------------------------------------------------------------------------
        | Save
        |--------------------------------------------------------------------------
        */

        $user = Yasuser::updateOrCreate(
            [
                'game_id' => $game->id,
                'refercode' => $externalRefercode,
            ],
            $newData
        );

        return [
            'user' => $user->fresh(),

            'hasChanges' =>
                !empty($changes),

            'changes' =>
                $changes,
        ];
    }
}