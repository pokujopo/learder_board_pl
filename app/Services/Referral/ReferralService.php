<?php

namespace App\Services\Referral;

use App\Models\Game;
use App\Models\Yasuser;
use Exception;
use Illuminate\Support\Facades\Http;

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

        if (!$externalData) {
            throw new Exception(
                'User not found in external API.'
            );
        }

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

        $response = Http::timeout(
                self::API_TIMEOUT
            )
            ->connectTimeout(10)
            ->retry(3, 100)
            ->post(
                $baseUrl . '/' . urlencode($refercode)
            );

        if (!$response->successful()) {
            throw new Exception(
                "External API returned status {$response->status()}."
            );
        }

        $data = $response->json();

        if (!isset($data['customer_all'])) {
            throw new Exception(
                'Invalid external API response.'
            );
        }

        return $data['customer_all'];
    }

    private function syncUser(
        string $refercode,
        array $externalData,
        Game $game
    ): array {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | We don't decide which company owns the refercode.
        | The external API response tells us who the customer is.
        |
        */

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
        | Find existing referral user
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

            $changes['status'] =
                'new_user_created';
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