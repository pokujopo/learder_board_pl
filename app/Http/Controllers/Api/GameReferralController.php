<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RefercodeAlreadyUsedException;
use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\CompetitionVerification;
use App\Models\Game;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GameReferralController extends Controller
{
    private const TOKEN_EXPIRATION_MINUTES = 10;

    public function verify(
        Request $request,
        Game $game,
        ReferralService $referralService,
    ) {
        /*
        |--------------------------------------------------------------------------
        | 1. Validate refercode
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'refercode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Competition must be active
        |--------------------------------------------------------------------------
        */

        if (!$game->is_active) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition is inactive.',
            ], 409);
        }

        $refercode = ReferralService::normalizeRefercode(
            $validated['refercode']
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Check whether this refercode is already registered
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | We check game_user because that table represents
        | a completed competition registration.
        |
        */

        $alreadyRegistered = \App\Models\GameUser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $refercode)
            ->where('refercode_verified', true)
            ->exists();

        if ($alreadyRegistered) {
            return response()->json([
                'status' => 409,
                'message' => 'This referral code has already been used in this competition.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Verify against external API
        |--------------------------------------------------------------------------
        */

        try {

            $customer = $referralService->verify(
                $refercode,
                $game
            );

            /*
            |--------------------------------------------------------------------------
            | 5. Generate temporary verification token
            |--------------------------------------------------------------------------
            */

            $plainToken = Str::random(64);

            $expiresAt = now()->addMinutes(
                self::TOKEN_EXPIRATION_MINUTES
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Store verification
            |--------------------------------------------------------------------------
            |
            | We store ONLY the hash of the token.
            | Frontend receives the plain token.
            |
            */

            $verification = CompetitionVerification::create([
                'game_id' => $game->id,

                'refercode' => $customer['refer_code'],

                'customer_name' =>
                    $customer['customer_name'] ?? null,

                'invitor_number' =>
                    isset($customer['invitor_number'])
                        ? (string) $customer['invitor_number']
                        : null,

                'token_hash' =>
                    hash('sha256', $plainToken),

                'expires_at' => $expiresAt,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Return verification token
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' => 200,

                'message' =>
                    'Referral code verified successfully.',

                'data' => [
                    'verification_token' => $plainToken,

                    'expires_at' =>
                        $verification->expires_at,

                    'competition' => [
                        'public_id' => $game->public_id,
                    ],
                ],
            ], 200);

        } catch (RefercodeAlreadyUsedException $e) {

            return response()->json([
                'status' => 409,
                'message' => $e->getMessage(),
            ], 409);

        } catch (RefercodeNotFoundException $e) {

            return response()->json([
                'status' => 404,
                'message' =>
                    'This referral code is not recognized.',
            ], 404);

        } catch (ReferralServiceUnavailableException $e) {

            Log::error(
                'Referral verification service unavailable',
                [
                    'game_id' => $game->id,
                    'refercode' => $refercode,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'status' => 503,
                'message' =>
                    'Unable to verify referral code at this time.',
            ], 503);

        } catch (Throwable $e) {

            Log::error(
                'Unexpected referral verification error',
                [
                    'game_id' => $game->id,
                    'refercode' => $refercode,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );

            return response()->json([
                'status' => 500,
                'message' =>
                    'Unable to verify referral code.',
            ], 500);
        }
    }
}