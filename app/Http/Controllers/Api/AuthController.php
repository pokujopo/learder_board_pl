<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwt
    ) {}

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'required|string|max:20|unique:users,phone_number',
            'location' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'phone_number' => $validated['phone'],
            'location'     => $validated['location'] ?? null,
            'password'     => $validated['password'],
            'role'         => 'user',
        ]);

        return $this->tokenResponse(
            $user,
            'User registered successfully.',
            201
        );
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (
            !$user ||
            !Hash::check($validated['password'], $user->password)
        ) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        return $this->tokenResponse(
            $user,
            'Login successful.'
        );
    }

    /**
     * Refresh access token using refresh token cookie.
     */
    public function refresh(Request $request)
    {
        $plainToken = $request->cookie('refresh_token');

        if (!$plainToken) {
            return response()->json([
                'status'  => 401,
                'message' => 'Refresh token is required.',
            ], 401);
        }

        $tokenHash = hash('sha256', $plainToken);

        $refreshToken = RefreshToken::where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$refreshToken) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        /*
         * Refresh token reuse detection.
         */
        if ($refreshToken->revoked_at) {
            RefreshToken::where('user_id', $refreshToken->user_id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);

            return response()->json([
                'status'  => 401,
                'message' => 'Refresh token reuse detected. Please sign in again.',
            ], 401);
        }

        /*
         * Check token expiration.
         */
        if ($refreshToken->expires_at->isPast()) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        /*
         * Rotate refresh token.
         */
        $newPlainToken = Str::random(96);

        $refreshToken->update([
            'revoked_at' => now(),
            'replaced_by' => hash('sha256', $newPlainToken),
        ]);

        $user = $refreshToken->user;

        $cookie = cookie(
            'refresh_token',
            $newPlainToken,
            (int) env('REFRESH_TOKEN_TTL_MINUTES', 43200),
            '/',
            '',
            (bool) env('COOKIE_SECURE', true),
            true,
            false,
            env('COOKIE_SAMESITE', 'lax')
        );

        return response()->json([
            'status'  => 200,
            'message' => 'Token refreshed.',
            'data'    => $this->accessTokenData($user),
        ])->withCookie($cookie);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $plainToken = $request->cookie('refresh_token');

        if ($plainToken) {
            RefreshToken::where(
                'token_hash',
                hash('sha256', $plainToken)
            )->update([
                'revoked_at' => now(),
            ]);
        }

        $request->user()?->tokens()->delete();

        return response()->json([
            'status'  => 200,
            'message' => 'Logout successful.',
        ])->withCookie(
            cookie()->forget('refresh_token')
        );
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data'   => [
                'user' => $this->user($request->user()),
            ],
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'status'  => 200,
            'message' => 'If the account exists, password reset instructions will be sent.',
        ]);
    }

    /**
     * Reset password.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $status = Password::reset(
            [
                'email'                 => $validated['email'],
                'password'              => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token'                 => $validated['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                /*
                 * Revoke all active refresh tokens
                 * after password reset.
                 */
                RefreshToken::where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                    ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status'  => 422,
                'message' => 'Unable to reset password.',
            ], 422);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Change authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check(
            $validated['current_password'],
            $user->password
        )) {
            return response()->json([
                'status'  => 422,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        /*
         * Revoke all active refresh tokens
         * after password change.
         */
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        return response()->json([
            'status'  => 200,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Generate access token data.
     */
    private function accessTokenData(User $user): array
    {
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

        return $this->jwt->issue(
            $user,
            $permissions
        ) + [
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Create access + refresh tokens.
     */
    private function tokenResponse(
        User $user,
        string $message,
        int $status = 200
    ) {
        $plainToken = Str::random(96);

        /*
         * Revoke previous refresh tokens.
         */
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        /*
         * Store hashed refresh token.
         */
        RefreshToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(
                (int) env('REFRESH_TOKEN_TTL_MINUTES', 43200)
            ),
        ]);

        $cookie = cookie(
            'refresh_token',
            $plainToken,
            (int) env('REFRESH_TOKEN_TTL_MINUTES', 43200),
            '/',
            '',
            (bool) env('COOKIE_SECURE', true),
            true,
            false,
            env('COOKIE_SAMESITE', 'lax')
        );

        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => [
                'user'  => $this->user($user),
                'token' => $this->accessTokenData($user),
            ],
        ], $status)->withCookie($cookie);
    }

    /**
     * Format user response.
     */
    private function user(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone_number,
            'location'          => $user->location,
            'role'              => $user->role,
            'email_verified_at' => $user->email_verified_at,
        ];
    }
}