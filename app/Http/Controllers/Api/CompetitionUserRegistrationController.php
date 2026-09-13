<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionVerification;
use App\Models\GameUser;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\JwtService;
use App\Services\Ranking\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompetitionUserRegistrationController extends Controller
{
    public function __construct(
        private RankingService $rankingService,
        private JwtService $jwt,
    ) {}

    public function register(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Validate registration data
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'verification_token' => [
                'required',
                'string',
                'size:64',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:30',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Normalize input
        |--------------------------------------------------------------------------
        */

        $validated['email'] = strtolower(
            trim($validated['email'])
        );

        $validated['phone_number'] = trim(
            $validated['phone_number']
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Hash verification token
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash(
            'sha256',
            $validated['verification_token']
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Find verification
        |--------------------------------------------------------------------------
        */

        $verification = CompetitionVerification::query()
            ->with('game')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 401,
                'message' => 'Invalid verification token.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Check token expiry
        |--------------------------------------------------------------------------
        */

        if (!$verification->expires_at) {
            return response()->json([
                'status' => 401,
                'message' => 'Invalid verification token.',
            ], 401);
        }

        if ($verification->expires_at->isPast()) {
            return response()->json([
                'status' => 410,
                'message' => 'Verification token has expired.',
            ], 410);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Get competition
        |--------------------------------------------------------------------------
        */

        $game = $verification->game;

        if (!$game) {
            return response()->json([
                'status' => 404,
                'message' => 'Competition no longer exists.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Competition must be currently active
        |--------------------------------------------------------------------------
        */

        if (!$game->is_active) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition is inactive.',
            ], 409);
        }

        $now = now();

        /*
        |--------------------------------------------------------------------------
        | Upcoming competition
        |--------------------------------------------------------------------------
        */

        if (
            $game->start_at !== null &&
            $now->lt($game->start_at)
        ) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition has not started yet.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Ended competition
        |--------------------------------------------------------------------------
        */

        if (
            $game->end_at !== null &&
            $now->gt($game->end_at)
        ) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition has already ended.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Find existing account by email
        |--------------------------------------------------------------------------
        */

        $existingEmailUser = User::query()
            ->where('email', $validated['email'])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 8. Find existing account by phone
        |--------------------------------------------------------------------------
        */

        $existingPhoneUser = User::query()
            ->where(
                'phone_number',
                $validated['phone_number']
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 9. Email and phone cannot belong to different accounts
        |--------------------------------------------------------------------------
        */

        if (
            $existingEmailUser &&
            $existingPhoneUser &&
            $existingEmailUser->id !== $existingPhoneUser->id
        ) {
            return response()->json([
                'status' => 409,
                'message' => 'Email and phone number belong to different accounts.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Resolve existing user
        |--------------------------------------------------------------------------
        */

        $existingUser =
            $existingEmailUser
            ?? $existingPhoneUser;

        /*
        |--------------------------------------------------------------------------
        | 11. Existing user cannot join another competition
        |--------------------------------------------------------------------------
        */

        if ($existingUser) {
            $hasCompetitionRegistration = GameUser::query()
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($hasCompetitionRegistration) {
                return response()->json([
                    'status' => 409,
                    'message' => 'This account is already registered in a competition.',
                ], 409);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 12. Create user + competition registration atomically
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(function () use (
            $validated,
            $tokenHash,
            $game,
            $existingUser
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock verification
            |--------------------------------------------------------------------------
            |
            | This prevents two requests using the same verification token
            | at the same time.
            |
            */

            $verification = CompetitionVerification::query()
                ->with('game')
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$verification) {
                abort(
                    response()->json([
                        'status' => 401,
                        'message' => 'Invalid verification token.',
                    ], 401)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Re-check token usage inside transaction
            |--------------------------------------------------------------------------
            */

            if ($verification->used_at !== null) {
                abort(
                    response()->json([
                        'status' => 409,
                        'message' =>
                            'This verification token has already been used.',
                    ], 409)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Re-check token expiry inside transaction
            |--------------------------------------------------------------------------
            */

            if (
                !$verification->expires_at ||
                $verification->expires_at->isPast()
            ) {
                abort(
                    response()->json([
                        'status' => 410,
                        'message' =>
                            'Verification token has expired.',
                    ], 410)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Re-check competition
            |--------------------------------------------------------------------------
            |
            | The competition may have been deactivated or ended
            | between the first check and this transaction.
            |
            */

            $lockedGame = $verification->game;

            if (!$lockedGame) {
                abort(
                    response()->json([
                        'status' => 404,
                        'message' => 'Competition no longer exists.',
                    ], 404)
                );
            }

            if (!$lockedGame->is_active) {
                abort(
                    response()->json([
                        'status' => 409,
                        'message' => 'This competition is inactive.',
                    ], 409)
                );
            }

            $now = now();

            if (
                $lockedGame->start_at !== null &&
                $now->lt($lockedGame->start_at)
            ) {
                abort(
                    response()->json([
                        'status' => 409,
                        'message' =>
                            'This competition has not started yet.',
                    ], 409)
                );
            }

            if (
                $lockedGame->end_at !== null &&
                $now->gt($lockedGame->end_at)
            ) {
                abort(
                    response()->json([
                        'status' => 409,
                        'message' =>
                            'This competition has already ended.',
                    ], 409)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create or reuse user
            |--------------------------------------------------------------------------
            */

            if ($existingUser) {
                /*
                |--------------------------------------------------------------------------
                | Lock existing user row
                |--------------------------------------------------------------------------
                */

                $user = User::query()
                    ->where('id', $existingUser->id)
                    ->lockForUpdate()
                    ->first();

                if (!$user) {
                    abort(
                        response()->json([
                            'status' => 404,
                            'message' => 'User account no longer exists.',
                        ], 404)
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Check if user joined any competition while waiting
                |--------------------------------------------------------------------------
                */

                $hasCompetitionRegistration = GameUser::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->exists();

                if ($hasCompetitionRegistration) {
                    abort(
                        response()->json([
                            'status' => 409,
                            'message' =>
                                'This account is already registered in a competition.',
                        ], 409)
                    );
                }
            } else {
                /*
                |--------------------------------------------------------------------------
                | Create new user
                |--------------------------------------------------------------------------
                */

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone_number' => $validated['phone_number'],
                    'password' => Hash::make(
                        $validated['password']
                    ),
                    'role' => 'user',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate registration in this competition
            |--------------------------------------------------------------------------
            */

            $alreadyRegistered = GameUser::query()
                ->where('game_id', $lockedGame->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($alreadyRegistered) {
                abort(
                    response()->json([
                        'status' => 409,
                        'message' =>
                            'You are already registered for this competition.',
                    ], 409)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create competition registration
            |--------------------------------------------------------------------------
            */

            $gameUser = GameUser::create([
                'user_id' => $user->id,
                'game_id' => $lockedGame->id,

                'refercode' => $verification->refercode,
                'refercode_verified' => true,
                'verified_at' => now(),

                'customer_name' => $verification->customer_name,
                'invitor_number' => $verification->invitor_number,

                'last_synced_at' => now(),
                'status' => 'active',

                /*
                 * Initial ranking state.
                 */
                'current_rank' => 0,
                'previous_rank' => 0,
                'rank_change' => 0,
                'rank_movement' => 'none',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Recalculate ranking
            |--------------------------------------------------------------------------
            */

            $this->rankingService->recalculate($lockedGame);

            $gameUser->refresh();

            /*
            |--------------------------------------------------------------------------
            | Mark verification token as used
            |--------------------------------------------------------------------------
            */

            $verification->update([
                'used_at' => now(),
            ]);

            return [
                'user' => $user,
                'game_user' => $gameUser,
                'game' => $lockedGame,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 13. Issue JWT
        |--------------------------------------------------------------------------
        */

        $user = $result['user'];
        $game = $result['game'];
        $gameUser = $result['game_user'];

        $permissions = $user->isAdmin()
            ? [
                'user:read',
                'user:update',
                'competition:read',
                'competition:join',
                'leaderboard:read',
                'referral:read',
                'reward:read',
                'admin:dashboard',
                'admin:competition',
                'admin:participant',
                'admin:referral',
                'admin:integration',
            ]
            : [
                'user:read',
                'user:update',
                'competition:read',
                'competition:join',
                'leaderboard:read',
                'referral:read',
                'reward:read',
            ];

        $token = $this->jwt->issue(
            $user,
            $permissions
        ) + [
            'token_type' => 'Bearer',
        ];

        /*
        |--------------------------------------------------------------------------
        | 14. Create refresh token
        |--------------------------------------------------------------------------
        */

        $plainRefreshToken = Str::random(96);

        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash(
                'sha256',
                $plainRefreshToken
            ),
            'expires_at' => now()->addMinutes(
                (int) env(
                    'REFRESH_TOKEN_TTL_MINUTES',
                    43200
                )
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 15. Set refresh token cookie
        |--------------------------------------------------------------------------
        */

        $cookie = cookie(
            'refresh_token',
            $plainRefreshToken,
            (int) env(
                'REFRESH_TOKEN_TTL_MINUTES',
                43200
            ),
            '/',
            '',
            (bool) env('COOKIE_SECURE', true),
            true,
            false,
            env('COOKIE_SAMESITE', 'lax')
        );

        /*
        |--------------------------------------------------------------------------
        | 16. Return response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'status' => 201,

            'message' =>
                'Registration completed successfully.',

            'data' => [

                /*
                |--------------------------------------------------------------------------
                | Auth
                |--------------------------------------------------------------------------
                */

                'token' => $token,

                /*
                |--------------------------------------------------------------------------
                | User
                |--------------------------------------------------------------------------
                */

                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                ],

                /*
                |--------------------------------------------------------------------------
                | Competition
                |--------------------------------------------------------------------------
                */

                'competition' => [
                    'public_id' => $game->public_id,
                    'name' => $game->name,
                    'status' => 'active',
                ],

                /*
                |--------------------------------------------------------------------------
                | Competition registration
                |--------------------------------------------------------------------------
                */

                'registration' => [
                    'id' => $gameUser->id,

                    'refercode' =>
                        $gameUser->refercode,

                    'verified' =>
                        (bool) $gameUser->refercode_verified,

                    'verified_at' =>
                        $gameUser->verified_at,

                    'rank' =>
                        (int) $gameUser->current_rank,

                    'rank_change' =>
                        (int) $gameUser->rank_change,

                    'rank_movement' =>
                        $gameUser->rank_movement,
                ],
            ],
        ], 201)->withCookie($cookie);
    }
}
