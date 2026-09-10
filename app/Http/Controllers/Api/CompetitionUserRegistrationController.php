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
        | 2. Find verification
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash(
            'sha256',
            $validated['verification_token']
        );

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
        | 3. Check token expiry
        |--------------------------------------------------------------------------
        */

        if ($verification->expires_at->isPast()) {
            return response()->json([
                'status' => 410,
                'message' => 'Verification token has expired.',
            ], 410);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Prevent token reuse
        |--------------------------------------------------------------------------
        */

        if ($verification->used_at !== null) {
            return response()->json([
                'status' => 409,
                'message' => 'This verification token has already been used.',
            ], 409);
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

        if (!$game->is_active) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition is inactive.',
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Find existing user
        |--------------------------------------------------------------------------
        */

        $existingUser = User::query()
            ->where('email', $validated['email'])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 7. Create user + competition registration atomically
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(function () use (
            $validated,
            $verification,
            $game,
            $existingUser
        ) {

            /*
            |--------------------------------------------------------------------------
            | Create or reuse user
            |--------------------------------------------------------------------------
            */

            if ($existingUser) {
                $user = $existingUser;
            } else {
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
            | Prevent duplicate competition registration
            |--------------------------------------------------------------------------
            */

            $alreadyRegistered = GameUser::query()
                ->where('game_id', $game->id)
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
                'game_id' => $game->id,

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

            $this->rankingService->recalculate($game);

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
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 8. Issue JWT
        |--------------------------------------------------------------------------
        */

        $user = $result['user'];

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
        | 9. Create refresh token
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
        | 10. Set refresh token cookie
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
        | 11. Return registration response
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
                    'status' => $game->is_active
                        ? 'active'
                        : 'inactive',
                ],

                /*
                |--------------------------------------------------------------------------
                | Competition registration
                |--------------------------------------------------------------------------
                */

                'registration' => [
                    'id' => $result['game_user']->id,
                    'refercode' =>
                        $result['game_user']->refercode,
                    'verified' =>
                        $result['game_user']->refercode_verified,
                    'verified_at' =>
                        $result['game_user']->verified_at,
                    'rank' =>
                        $result['game_user']->current_rank,
                    'rank_change' =>
                        $result['game_user']->rank_change,
                    'rank_movement' =>
                        $result['game_user']->rank_movement,
                ],
            ],
        ], 201)->withCookie($cookie);
    }
}



/**
 * Competition User Registration Controller
 */
/*
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionVerification;
use App\Models\GameUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Ranking\RankingService;
use Illuminate\Support\Facades\Hash;


class CompetitionUserRegistrationController extends Controller
{
    public function __construct(
    private RankingService $rankingService,
) {
}
    public function register(Request $request)
    {
     

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


        $tokenHash = hash(
            'sha256',
            $validated['verification_token']
        );

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

       

        if ($verification->expires_at->isPast()) {
            return response()->json([
                'status' => 410,
                'message' => 'Verification token has expired.',
            ], 410);
        }


        if ($verification->used_at !== null) {
            return response()->json([
                'status' => 409,
                'message' => 'This verification token has already been used.',
            ], 409);
        }

       
        $game = $verification->game;

        if (!$game) {
            return response()->json([
                'status' => 404,
                'message' => 'Competition no longer exists.',
            ], 404);
        }

        if (!$game->is_active) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition is inactive.',
            ], 409);
        }

       

        $existingUser = User::query()
            ->where('email', $validated['email'])
            ->first();

      
        $result = DB::transaction(function () use (
            $validated,
            $verification,
            $game,
            $existingUser
        ) {

           

            if ($existingUser) {
                $user = $existingUser;
            } else {
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

            

            


            $alreadyRegistered = GameUser::query()
                ->where('game_id', $game->id)
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


           $gameUser = GameUser::create([
            'user_id' => $user->id,
            'game_id' => $verification->game_id,

            'refercode' => $verification->refercode,
            'refercode_verified' => true,
            'verified_at' => now(),

            'customer_name' => $verification->customer_name,
            'invitor_number' => $verification->invitor_number,

            'last_synced_at' => now(),
            'status' => 'active',

            // Initial ranking state
            'current_rank' => 0,
            'previous_rank' => 0,
            'rank_change' => 0,
            'rank_movement' => 'none',
        ]);

        $this->rankingService->recalculate($game);

        $gameUser->refresh();

           

            $verification->update([
                'used_at' => now(),
            ]);

            return [
                'user' => $user,
                'game_user' => $gameUser,
            ];
        });

        


        return response()->json([
            'status' => 201,
            'message' =>
                'Registration completed successfully.',

            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'phone_number' =>
                        $result['user']->phone_number,
                   
                ],

                'competition' => [
                    'public_id' => $game->public_id,
                    'name' => $game->name,
                ],

                'registration' => [
                    'id' => $result['game_user']->id,
                    'refercode' =>
                        $result['game_user']->refercode,
                    'verified' =>
                        $result['game_user']->refercode_verified,
                    'verified_at' =>
                        $result['game_user']->verified_at,
                ],
            ],
        ], 201);
    }
}*/