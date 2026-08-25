<?php

namespace App\Services\Referral;

use App\Models\Yasuser;
use Exception;
use Illuminate\Support\Facades\Http;

class ReferralService
{
    private const API_TIMEOUT = 30;

    public function fetchAndSync(string $refercode): array
    {
        $externalData = $this->fetchFromExternalApi($refercode);

        if (!$externalData) {
            throw new Exception('User not found in external API.');
        }

        return $this->syncUser($refercode, $externalData);
    }

    private function fetchFromExternalApi(string $refercode): array
    {
        $baseUrl = config(
            'services.referral.url',
            'http://127.0.0.1:8001/api/yas/user'
        );

        $response = Http::timeout(self::API_TIMEOUT)
            ->connectTimeout(10)
            ->retry(3, 100)
            ->post($baseUrl . '/' . urlencode($refercode));

        if (!$response->successful()) {
            throw new Exception(
                "External API returned status {$response->status()}."
            );
        }

        $data = $response->json();

        if (!isset($data['customer_all'])) {
            throw new Exception(
                "Invalid external API response."
            );
        }

        return $data['customer_all'];
    }

    private function syncUser(
        string $refercode,
        array $externalData
    ): array {
        $existingUser = Yasuser::where(
            'refercode',
            $refercode
        )->first();

        $newData = [
            'refercode' => $externalData['refer_code'] ?? $refercode,

            'compitetor_name' =>
                $externalData['customer_name'] ?? null,

            'total_inviter_number' =>
                (int) ($externalData['invitor_number'] ?? 0),

            'last_synced_at' => now(),
        ];

        $changes = [];

        if ($existingUser) {
            foreach ([
                'compitetor_name',
                'total_inviter_number',
            ] as $field) {
                if ($existingUser->{$field} != $newData[$field]) {
                    $changes[$field] = [
                        'old' => $existingUser->{$field},
                        'new' => $newData[$field],
                    ];
                }
            }
        } else {
            $changes['status'] = 'new_user_created';
        }

        $user = Yasuser::updateOrCreate(
            ['refercode' => $refercode],
            $newData
        );

        return [
            'user' => $user->fresh(),
            'hasChanges' => !empty($changes),
            'changes' => $changes,
        ];
    }
}