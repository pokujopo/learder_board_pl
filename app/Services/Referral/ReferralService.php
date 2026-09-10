<?php

namespace App\Services\Referral;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\Game;
use App\Models\GameUser;
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
     * Verify referral code against external service.
     */
    public function verify(
        string $refercode,
        Game $game
    ): array {
        $refercode = self::normalizeRefercode($refercode);

        return $this->fetchFromExternalApi(
            $refercode,
            $game
        );
    }

    /**
     * Fetch latest external data and synchronize
     * the existing game_user record.
     */
    public function fetchAndSyncGameUser(
        GameUser $gameUser
    ): array {
        $refercode = self::normalizeRefercode(
            $gameUser->refercode
        );

        $game = $gameUser->game;

        $externalData = $this->fetchFromExternalApi(
            $refercode,
            $game
        );

        $newData = [
            'customer_name' => $externalData['customer_name'],
            'invitor_number' => (string) $externalData['invitor_number'],
            'last_synced_at' => now(),
            'status' => 'active',
        ];

        $changes = [];

        /*
         * Compare old data with external data.
         */
        foreach (
            [
                'customer_name',
                'invitor_number',
                'status',
            ] as $field
        ) {
            $oldValue = $gameUser->{$field};
            $newValue = $newData[$field];

            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        /*
         * Update the SAME game_user.
         *
         * No new user is created.
         * No new referral record is created.
         */
        $gameUser->update($newData);

        return [
            'game_user' => $gameUser->fresh(),
            'hasChanges' => !empty($changes),
            'changes' => $changes,
        ];
    }

    public static function normalizeRefercode(
        string $refercode
    ): string {
        return strtoupper(trim($refercode));
    }

    /**
     * Call external referral API.
     *
     * POST:
     *
     * {external_api_base_url}/{refercode}
     */
    private function fetchFromExternalApi(
        string $refercode,
        Game $game
    ): array {
        $baseUrl = trim(
            (string) $game->external_api_base_url
        );

        if ($baseUrl === '') {

            Log::critical(
                'Referral API URL is not configured',
                [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Referral service is not configured.'
            );
        }

        $url = rtrim($baseUrl, '/') . '/'
            . rawurlencode($refercode);

        try {

            $response = $this
                ->httpClient()
                ->post($url);

        } catch (ConnectionException $e) {

            Log::error(
                'Referral API connection failed',
                [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'refercode' => $refercode,
                    'error' => $e->getMessage(),
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.',
                previous: $e
            );
        }

        /*
         * Referral does not exist.
         */
        if ($response->status() === 404) {

            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        /*
         * External server problem.
         */
        if ($response->serverError()) {

            Log::error(
                'Referral API server error',
                [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'refercode' => $refercode,
                    'status' => $response->status(),
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Referral service is unavailable.'
            );
        }

        /*
         * Client error.
         */
        if ($response->clientError()) {

            Log::warning(
                'Referral API client error',
                [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'refercode' => $refercode,
                    'status' => $response->status(),
                ]
            );

            throw new RefercodeNotFoundException(
                'Refercode could not be verified.'
            );
        }

        if (!$response->successful()) {

            Log::error(
                'Unexpected referral API response',
                [
                    'game_id' => $game->id,
                    'game_code' => $game->code,
                    'refercode' => $refercode,
                    'status' => $response->status(),
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Referral service returned an unexpected response.'
            );
        }

        $payload = $response->json();

        if (!is_array($payload)) {

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        /*
         * External application-level 404.
         */
        if (
            (int) ($payload['status'] ?? 200)
            === 404
        ) {
            throw new RefercodeNotFoundException(
                'Refercode was not found.'
            );
        }

        /*
         * Expected response:
         *
         * {
         *   "status": 200,
         *   "customer_all": {
         *      "refer_code": "ABC83",
         *      "customer_name": "john doe",
         *      "invitor_number": 30000
         *   }
         * }
         */
        $customer = $payload['customer_all'] ?? null;

        if (!is_array($customer)) {

            Log::error(
                'Referral API response missing customer_all',
                [
                    'game_id' => $game->id,
                    'refercode' => $refercode,
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        /*
         * Validate returned refercode.
         */
        $externalRefercode = isset(
            $customer['refer_code']
        )
            ? self::normalizeRefercode(
                (string) $customer['refer_code']
            )
            : '';

        if ($externalRefercode === '') {

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        /*
         * Security check:
         *
         * external API must return the SAME refercode
         * we requested.
         */
        if ($externalRefercode !== $refercode) {

            Log::warning(
                'Referral API returned different refercode',
                [
                    'game_id' => $game->id,
                    'requested_refercode' => $refercode,
                    'returned_refercode' => $externalRefercode,
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Referral service returned inconsistent data.'
            );
        }

        /*
         * invitor_number must exist.
         *
         * IMPORTANT:
         * Do NOT cast to int because values may be
         * larger than a 32-bit integer.
         */
        $invitorNumber =
            $customer['invitor_number'] ?? null;

        if (
            $invitorNumber === null ||
            !is_numeric($invitorNumber) ||
            (float) $invitorNumber < 0
        ) {

            Log::error(
                'Referral API returned invalid invitor_number',
                [
                    'game_id' => $game->id,
                    'refercode' => $refercode,
                ]
            );

            throw new ReferralServiceUnavailableException(
                'Invalid referral service response.'
            );
        }

        return [
            'refer_code' => $externalRefercode,

            'customer_name' =>
                isset($customer['customer_name'])
                    ? trim(
                        (string) $customer['customer_name']
                    )
                    : null,

            /*
             * Store as string.
             */
            'invitor_number' =>
                (string) $invitorNumber,
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
                    return $exception
                        instanceof ConnectionException;
                },
                throw: true
            );
    }
}