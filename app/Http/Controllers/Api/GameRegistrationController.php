<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        try {
            return DB::transaction(function () use (
                $validated,
                $game,
                $user
            ) {
                /*
                |--------------------------------------------------------------------------
                | Check existing registration
                |--------------------------------------------------------------------------
                */

                $existingRegistration = GameUser::where(
                    'user_id',
                    $user->id
                )
                    ->where('game_id', $game->id)
                    ->first();

                if (
                    $existingRegistration &&
                    $existingRegistration->refercode_verified
                ) {
                    return response()->json([
                        'status' => 409,
                        'message' =>
                            'You are already registered for this game.',
                    ], 409);
                }

                /*
                |--------------------------------------------------------------------------
                | Verify refercode through external API
                |--------------------------------------------------------------------------
                */

                $result = $this->referralService->fetchAndSync(
                    $validated['refercode'],
                    $game
                );

                $yasuser = $result['user'];

                /*
                |--------------------------------------------------------------------------
                | Create / update game registration
                |--------------------------------------------------------------------------
                */

                $registration = GameUser::updateOrCreate(
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

                /*
                |--------------------------------------------------------------------------
                | Invalidate ranking cache
                |--------------------------------------------------------------------------
                */

                if ($result['hasChanges']) {
                    cache()->forget(
                        'yasuser_ranking_game_' . $game->id
                    );
                }

                return response()->json([
                    'status' => 200,
                    'message' =>
                        'Refercode verified successfully.',
                    'data' => [
                        'game' => $game,
                        'user' => $yasuser,
                        'registration' => $registration,
                    ],
                ]);
            });

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'status' => 500,
                'message' =>
                    'Unable to verify refercode.',
            ], 500);
        }
    }
}