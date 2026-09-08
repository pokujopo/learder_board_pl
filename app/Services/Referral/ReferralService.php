<?php

namespace App\Services\Referral;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\Game;
use App\Models\Yasuser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    private const API_TIMEOUT = 10;
    private const CONNECT_TIMEOUT = 3;
    private const MAX_RETRIES = 2;

    /**
     * Verify a referral code against the external service and synchronize it locally.
     *
     * The external API contract is:
     * POST {external_api_base_url}/{REFERCODE}
     *
     * Example:
     * POST https://example.com/api/yas/ABC823
     */
    public function verify(string $refercode, Game $game): array
    {
        $refercode = self::normalizeRefercode($refercode);
        return $this->fetchFromExternalApi($refercode, $game);
    }

    public function fetchAndSync(string $refercode, Game $game): array
    {
        $refercode = self::normalizeRefercode($refercode);
        $customer = $this->verify($refercode, $game);

        return $this->syncUser($refercode, $customer, $game);
    }

    public static function normalizeRefercode(string $refercode): string
    {
        return strtoupper(trim($refercode));
    }

    private function fetchFromExternalApi(string $refercode, Game $game): array
    {
        $baseUrl = trim((string) $game->external_api_base_url);

        if ($baseUrl === '') {
            Log::critical('Referral API URL is not configured', [
                'game_id' => $game->id,
                'game_code' => $game->code,
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is not configured.'
            );
        }

        $url = rtrim($baseUrl, '/') . '/' . rawurlencode($refercode);

        try {
            $response = $this->httpClient()->post($url);
        } catch (ConnectionException $e) {
            Log::error('Referral API connection failed', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.',
                previous: $e
            );
        }

        if ($response->status() === 404) {
            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        if ($response->serverError()) {
            Log::error('Referral API server error', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        if ($response->clientError()) {
            Log::warning('Referral API client error', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new RefercodeNotFoundException(
                'Refercode could not be verified.'
            );
        }

        if (!$response->successful()) {
            Log::error('Unexpected referral API response', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'refercode' => $refercode,
                'status' => $response->status(),
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service returned an unexpected response.'
            );
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            Log::error('Referral API returned invalid JSON', [
                'game_id' => $game->id,
                'refercode' => $refercode,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        // Support the external API's application-level status as well as HTTP status.
        if ((int) ($payload['status'] ?? 200) === 404) {
            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        $customer = $payload['customer_all'] ?? null;

        if (!is_array($customer)) {
            Log::error('Referral API response is missing customer_all', [
                'game_id' => $game->id,
                'refercode' => $refercode,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        $externalRefercode = isset($customer['refer_code'])
            ? self::normalizeRefercode((string) $customer['refer_code'])
            : '';

        if ($externalRefercode === '') {
            Log::error('Referral API response is missing refer_code', [
                'game_id' => $game->id,
                'refercode' => $refercode,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        // Prevent an external integration from returning a different referral code
        // than the one the user requested.
        if ($externalRefercode !== $refercode) {
            Log::warning('Referral API returned a different refercode', [
                'game_id' => $game->id,
                'requested_refercode' => $refercode,
                'returned_refercode' => $externalRefercode,
            ]);

            throw new ReferralServiceUnavailableException(
                'Referral service returned inconsistent data.'
            );
        }

        $invitorNumber = $customer['invitor_number'] ?? null;

        if ($invitorNumber === null || !is_numeric($invitorNumber) || (int) $invitorNumber < 0) {
            Log::error('Referral API returned invalid invitor_number', [
                'game_id' => $game->id,
                'refercode' => $refercode,
            ]);

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        return [
            'refer_code' => $externalRefercode,
            'customer_name' => isset($customer['customer_name'])
                ? trim((string) $customer['customer_name'])
                : null,
            'invitor_number' => (int) $invitorNumber,
        ];
    }

    private function httpClient(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(self::API_TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->retry(
                self::MAX_RETRIES,
                250,
                function ($exception) {
                    return $exception instanceof ConnectionException;
                },
                throw: true
            );
    }

    private function syncUser(
        string $refercode,
        array $externalData,
        Game $game
    ): array {
        $refercode = self::normalizeRefercode($refercode);

        $newData = [
            'game_id' => $game->id,
            'refercode' => $refercode,
            'compitetor_name' => $externalData['customer_name'] ?? null,
            'total_inviter_number' => (int) $externalData['invitor_number'],
            'last_synced_at' => now(),
            'status' => 'active',
        ];

        $existingUser = Yasuser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $refercode)
            ->first();

        $changes = [];

        if (!$existingUser) {
            $changes['status'] = 'new_user_created';
        } else {
            foreach (['compitetor_name', 'total_inviter_number', 'status'] as $field) {
                if ($existingUser->{$field} != $newData[$field]) {
                    $changes[$field] = [
                        'old' => $existingUser->{$field},
                        'new' => $newData[$field],
                    ];
                }
            }
        }

        $user = Yasuser::updateOrCreate(
            [
                'game_id' => $game->id,
                'refercode' => $refercode,
            ],
            $newData
        );

        return [
            'user' => $user->fresh(),
            'hasChanges' => !empty($changes),
            'changes' => $changes,
        ];
    }
}
