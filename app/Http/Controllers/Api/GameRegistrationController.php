<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameRegistrationController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {
    }

    public function verifyRefercode(
        Request $request,
        Game $game
    ) {
        $validated = $request->validate([
            'refercode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
        ]);

        if (!$game->is_active) {
            return response()->json([
                'status' => 403,
                'message' => 'This game is currently inactive.',
            ], 403);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $refercode = $validated['refercode'];

        /*
        |--------------------------------------------------------------------------
        | 1. Check kama user huyu tayari amesharegister
        |--------------------------------------------------------------------------
        */

        $existingUserRegistration = GameUser::query()
            ->where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('refercode_verified', true)
            ->first();

        if ($existingUserRegistration) {
            return response()->json([
                'status' => 409,
                'message' => 'You are already registered for this game.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Check kama refercode tayari imetumiwa
        |--------------------------------------------------------------------------
        */

        $refercodeAlreadyTaken = GameUser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $refercode)
            ->where('refercode_verified', true)
            ->exists();

        if ($refercodeAlreadyTaken) {
            return response()->json([
                'status' => 409,
                'message' => 'This refercode has already been taken.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Verify against external API
        |--------------------------------------------------------------------------
        */

        try {

            $result = $this->referralService->fetchAndSync(
                $refercode,
                $game
            );

            /*
            |--------------------------------------------------------------------------
            | 4. External API confirmed refercode
            |--------------------------------------------------------------------------
            */

            $yasuser = $result['user'];

            /*
            |--------------------------------------------------------------------------
            | 5. Save registration
            |--------------------------------------------------------------------------
            */

            $registration = DB::transaction(function () use (
                $user,
                $game,
                $yasuser
            ) {

                /*
                | Race-condition protection:
                | check again inside transaction before insert.
                */

                $taken = GameUser::query()
                    ->where('game_id', $game->id)
                    ->where('refercode', $yasuser->refercode)
                    ->where('refercode_verified', true)
                    ->lockForUpdate()
                    ->exists();

                if ($taken) {
                    return null;
                }

                return GameUser::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                    ],
                    [
                        'refercode' => $yasuser->refercode,
                        'refercode_verified' => true,
                        'verified_at' => now(),
                    ]
                );
            });

            /*
            |--------------------------------------------------------------------------
            | Someone took it between our first check and insert
            |--------------------------------------------------------------------------
            */

            if (!$registration) {
                return response()->json([
                    'status' => 409,
                    'message' => 'This refercode has already been taken.',
                ], 409);
            }

            /*
            |--------------------------------------------------------------------------
            | Invalidate ranking cache
            |--------------------------------------------------------------------------
            */

            if ($result['hasChanges']) {
                cache()->forget(
                    'game:' . $game->id . ':ranking'
                );
            }

            return response()->json([
                'status' => 200,
                'message' => 'Refercode verified successfully.',
                'data' => [
                    'game' => [
                        'id' => $game->id,
                        'name' => $game->name,
                        'code' => $game->code,
                    ],

                    'user' => $yasuser,

                    'registration' => $registration,
                ],
            ], 200);

        } catch (RefercodeNotFoundException $e) {

            /*
            |--------------------------------------------------------------------------
            | External API imesema refercode haipo
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' => 404,
                'message' => 'This refercode is not recognized.',
            ], 404);

        } catch (ReferralServiceUnavailableException $e) {

            /*
            |--------------------------------------------------------------------------
            | External API down / unavailable
            |--------------------------------------------------------------------------
            */

            Log::error('Referral service unavailable.', [
                'game_id' => $game->id,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to verify refercode.',
            ], 500);

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Unexpected internal error
            |--------------------------------------------------------------------------
            */

            Log::error('Unexpected referral verification error.', [
                'game_id' => $game->id,
                'refercode' => $refercode,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to verify refercode.',
            ], 500);
        }
    }
}