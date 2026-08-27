<?php

namespace App\Services\Referral;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\Game;
use App\Models\Yasuser;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    private const API_TIMEOUT = 30;

    public function fetchAndSync(
        string $refercode,
        Game $game
    ): array {

        /*
        |--------------------------------------------------------------------------
        | External API is the source of truth
        |--------------------------------------------------------------------------
        */

        $externalData = $this->fetchFromExternalApi(
            $refercode
        );

        return $this->syncUser(
            $refercode,
            $externalData,
            $game
        );
    }

    private function fetchFromExternalApi(
        string $refercode
    ): array {

        $baseUrl = config(
            'services.referral.url',
            'http://127.0.0.1:8001/api/yas/user'
        );

        try {

            $response = Http::timeout(
                self::API_TIMEOUT
            )
            ->connectTimeout(10)
            ->retry(
                3,
                100,
                throw: false
            )
            ->post(
                $baseUrl . '/' . urlencode($refercode)
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {

            Log::error('Referral external API connection failed', [
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | External API unavailable
        |--------------------------------------------------------------------------
        */

        if ($response->serverError()) {

            Log::error('Referral external API server error', [
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Refercode does not exist
        |--------------------------------------------------------------------------
        */

        if ($response->notFound()) {

            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Any other client error
        |--------------------------------------------------------------------------
        */

        if ($response->clientError()) {

            throw new RefercodeNotFoundException(
                'Refercode could not be verified.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate successful response
        |--------------------------------------------------------------------------
        */

        if (!$response->successful()) {

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | Validate external API structure
        |--------------------------------------------------------------------------
        */

        if (!isset($data['customer_all']) ||
            !is_array($data['customer_all'])) {

            Log::error('Invalid referral API response', [
                'refercode' => $refercode,
                'response' => $data,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        return $data['customer_all'];
    }

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
                        'old' =>
                            $existingUser->{$field},

                        'new' =>
                            $newData[$field],
                    ];
                }
            }

        } else {

            $changes['status'] =
                'new_user_created';
        }

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