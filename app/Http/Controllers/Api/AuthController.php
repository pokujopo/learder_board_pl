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
use App\Models\GameUser;
use Fouladgar\OTP\Facades\OTP;
use Fouladgar\OTP\Exceptions\OTPException;

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
 *
 * Step 1:
 * Validate email/phone + password and send OTP.
 *
 * OTP can be delivered by:
 * - email
 * - SMS
 *
 * JWT is NOT issued until OTP is verified.
 */
public function login(Request $request)
{
    $validated = $request->validate([
        'identifier' => 'required|string',
        'password'   => 'required|string',
        'otp_method' => 'nullable|in:email,phone',
    ]);

    $identifier = trim($validated['identifier']);

    /*
     * Find user by email or phone number.
     */
    $user = User::query()
        ->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('phone_number', $identifier);
        })
        ->first();

    /*
     * Validate credentials.
     */
    if (
        !$user ||
        !Hash::check($validated['password'], $user->password)
    ) {
        return response()->json([
            'status'  => 401,
            'message' => 'Invalid credentials.',
        ], 401);
    }

    /*
     * ----------------------------------------------------------
     * OTP ALREADY VERIFIED
     * ----------------------------------------------------------
     *
     * User has completed the one-time login verification.
     *
     * Do NOT send OTP again.
     */
    if ($user->login_otp_verified_at !== null) {
        return $this->tokenResponse(
            $user,
            'Login successful.'
        );
    }

    /*
     * ----------------------------------------------------------
     * FIRST LOGIN
     * ----------------------------------------------------------
     *
     * User has never completed login OTP verification.
     */
    $otpMethod = $validated['otp_method'] ?? 'email';

    /*
     * EMAIL OTP
     */
    if ($otpMethod === 'email') {
        try {
            $sent = OTP::purpose('login_')
                ->channel('mail')
                ->send($user->email);

            if (!$sent) {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Unable to send verification code.',
                ], 500);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Verification code sent to your email.',
                'data'    => [
                    'otp_required' => true,
                    'otp_method'   => 'email',
                    'identifier'   => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }
    }

    /*
     * SMS OTP
     */
    try {
        $sent = OTP::purpose('login_')
            ->channel('sms')
            ->send($user->phone_number);

        if (!$sent) {
            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Verification code sent to your phone.',
            'data'    => [
                'otp_required' => true,
                'otp_method'   => 'phone',
                'identifier'   => $user->phone_number,
            ],
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status'  => 500,
            'message' => 'Unable to send verification code.',
        ], 500);
    }
}
/**
 * Verify login OTP and issue JWT + refresh token.
 */
public function verifyLoginOtp(Request $request)
{
    $validated = $request->validate([
        'identifier' => 'required|string',
        'otp'        => 'required|string',
    ]);

    $identifier = trim($validated['identifier']);

    /*
     * Find user by email or phone.
     */
    $user = User::query()
        ->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('phone_number', $identifier);
        })
        ->first();

    if (!$user) {
        return response()->json([
            'status'  => 401,
            'message' => 'Invalid verification code.',
        ], 401);
    }

    /*
     * Determine OTP recipient.
     */
    if ($identifier === $user->email) {
        $otpRecipient = $user->email;
    } elseif ($identifier === $user->phone_number) {
        $otpRecipient = $user->phone_number;
    } else {
        return response()->json([
            'status'  => 401,
            'message' => 'Invalid verification code.',
        ], 401);
    }

    /*
     * Verify OTP.
     */
    try {
        $valid = OTP::purpose('login_')
            ->validate(
                $otpRecipient,
                $validated['otp']
            );

        if (!$valid) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid verification code.',
            ], 401);
        }
    } catch (OTPException $e) {
        return response()->json([
            'status'  => 401,
            'message' => 'Invalid or expired verification code.',
        ], 401);
    }

    /*
     * ----------------------------------------------------------
     * ONE-TIME LOGIN VERIFICATION COMPLETED
     * ----------------------------------------------------------
     */
    $user->update([
        'login_otp_verified_at' => now(),
    ]);

    /*
     * Issue JWT + refresh token.
     */
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
 * Send password reset OTP.
 *
 * OTP can be delivered by:
 * - email
 * - phone
 */
public function forgotPassword(Request $request)
{
    $validated = $request->validate([
        'identifier' => 'required|string',
        'otp_method' => 'nullable|in:email,phone',
    ]);

    $identifier = trim($validated['identifier']);

    /*
     * Find user by email or phone.
     */
    $user = User::query()
        ->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('phone_number', $identifier);
        })
        ->first();

    /*
     * Do not reveal whether the account exists.
     */
    if (!$user) {
        return response()->json([
            'status'  => 200,
            'message' => 'If the account exists, a verification code will be sent.',
        ]);
    }

    /*
     * Email remains the default for backward compatibility.
     */
    $otpMethod = $validated['otp_method'] ?? 'email';

    /*
     * ----------------------------------------------------------
     * EMAIL OTP
     * ----------------------------------------------------------
     */
    if ($otpMethod === 'email') {
        try {
            $sent = OTP::purpose('password_reset_')
                ->channel('mail')
                ->send($user->email);

            if (!$sent) {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Unable to send verification code.',
                ], 500);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Verification code sent to your email.',
                'data'    => [
                    'otp_required' => true,
                    'otp_method'   => 'email',
                    'identifier'   => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }
    }

    /*
     * ----------------------------------------------------------
     * SMS OTP
     * ----------------------------------------------------------
     */
    try {
        $sent = OTP::purpose('password_reset_')
            ->channel('sms')
            ->send($user->phone_number);

        if (!$sent) {
            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Verification code sent to your phone.',
            'data'    => [
                'otp_required' => true,
                'otp_method'   => 'phone',
                'identifier'   => $user->phone_number,
            ],
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status'  => 500,
            'message' => 'Unable to send verification code.',
        ], 500);
    }
}
   
/**
 * Verify password reset OTP and set a new password.
 */
public function resetPassword(Request $request)
{
    $validated = $request->validate([
        'identifier'          => 'required|string',
        'otp'                 => 'required|string',
        'otp_method'          => 'required|in:email,phone',
        'password'            => 'required|string|min:8|confirmed',
        'password_confirmation' => 'required|string',
    ]);

    $identifier = trim($validated['identifier']);

    /*
     * Find user by email or phone.
     */
    $user = User::query()
        ->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('phone_number', $identifier);
        })
        ->first();

    if (!$user) {
        return response()->json([
            'status'  => 422,
            'message' => 'Unable to reset password.',
        ], 422);
    }

    /*
     * Determine the OTP recipient.
     */
    if ($validated['otp_method'] === 'email') {
        $otpRecipient = $user->email;

        /*
         * Prevent using phone identifier with email OTP.
         */
        if ($identifier !== $user->email) {
            return response()->json([
                'status'  => 422,
                'message' => 'Invalid verification request.',
            ], 422);
        }
    } else {
        $otpRecipient = $user->phone_number;

        /*
         * Prevent using email identifier with phone OTP.
         */
        if ($identifier !== $user->phone_number) {
            return response()->json([
                'status'  => 422,
                'message' => 'Invalid verification request.',
            ], 422);
        }
    }

    try {
        $valid = OTP::purpose('password_reset_')
            ->validate(
                $otpRecipient,
                $validated['otp']
            );

        if (!$valid) {
            return response()->json([
                'status'  => 422,
                'message' => 'Invalid verification code.',
            ], 422);
        }
    } catch (OTPException $e) {
        return response()->json([
            'status'  => 422,
            'message' => 'Invalid or expired verification code.',
        ], 422);
    }

    /*
     * Change password.
     */
    $user->update([
        'password' => $validated['password'],
    ]);

    /*
     * Revoke all active refresh tokens
     * after password reset.
     */
    RefreshToken::where('user_id', $user->id)
        ->whereNull('revoked_at')
        ->update([
            'revoked_at' => now(),
        ]);

    return response()->json([
        'status'  => 200,
        'message' => 'Password reset successfully.',
    ]);
}

    /**
 * Send change-password OTP.
 *
 * OTP can be delivered by:
 * - email
 * - phone
 */
public function changePassword(Request $request)
{
    $validated = $request->validate([
        'current_password' => 'required|string',
        'otp_method'       => 'required|in:email,phone',
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

    $otpMethod = $validated['otp_method'];

    if ($otpMethod === 'email') {
        try {
            $sent = OTP::purpose('change_password_')
                ->channel('mail')
                ->send($user->email);

            if (!$sent) {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Unable to send verification code.',
                ], 500);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Verification code sent to your email.',
                'data'    => [
                    'otp_required' => true,
                    'otp_method'   => 'email',
                    'identifier'   => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }
    }

    try {
        $sent = OTP::purpose('change_password_')
            ->channel('sms')
            ->send($user->phone_number);

        if (!$sent) {
            return response()->json([
                'status'  => 500,
                'message' => 'Unable to send verification code.',
            ], 500);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Verification code sent to your phone.',
            'data'    => [
                'otp_required' => true,
                'otp_method'   => 'phone',
                'identifier'   => $user->phone_number,
            ],
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status'  => 500,
            'message' => 'Unable to send verification code.',
        ], 500);
    }
}


/**
 * Verify change-password OTP and update password.
 */
public function verifyChangePasswordOtp(Request $request)
{
    $validated = $request->validate([
        'otp'                   => 'required|string',
        'otp_method'            => 'required|in:email,phone',
        'password'              => 'required|string|min:8|confirmed',
        'password_confirmation' => 'required|string',
    ]);

    $user = $request->user();

    if ($validated['otp_method'] === 'email') {
        $otpRecipient = $user->email;
    } else {
        $otpRecipient = $user->phone_number;
    }

    try {
        $valid = OTP::purpose('change_password_')
            ->validate(
                $otpRecipient,
                $validated['otp']
            );

        if (!$valid) {
            return response()->json([
                'status'  => 422,
                'message' => 'Invalid verification code.',
            ], 422);
        }
    } catch (OTPException $e) {
        return response()->json([
            'status'  => 422,
            'message' => 'Invalid or expired verification code.',
        ], 422);
    }

    $user->update([
        'password' => $validated['password'],
    ]);

    // Revoke all existing refresh tokens.
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

    /*
     * Refresh token cookie.
     */
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

    /*
     * --------------------------------------------------------------
     * Find user's competition registration.
     * --------------------------------------------------------------
     *
     * GameUser connects:
     *
     * User -> GameUser -> Game
     *
     * Prefer an active competition.
     * If there is no active competition, use the latest registration.
     */
    $gameUser = GameUser::query()
        ->with('game')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->latest('id')
        ->first();

    /*
     * If no active registration exists, try the latest one.
     */
    if (!$gameUser) {
        $gameUser = GameUser::query()
            ->with('game')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    /*
     * Build competition response.
     */
    $competition = null;
    $registration = null;

    if ($gameUser && $gameUser->game) {
        $game = $gameUser->game;

        $competition = [
            'public_id' => $game->public_id,
            'name'      => $game->name,
            'status'    => $game->is_active
                ? 'active'
                : 'inactive',
        ];

        $registration = [
            'id' => $gameUser->id,

            'refercode' =>
                $gameUser->refercode,

            'verified' =>
                (bool) $gameUser->refercode_verified,

            'verified_at' =>
                $gameUser->verified_at,

            'rank' =>
                $gameUser->current_rank,

            'previous_rank' =>
                $gameUser->previous_rank,

            'rank_change' =>
                $gameUser->rank_change,

            'rank_movement' =>
                $gameUser->rank_movement,
        ];
    }

    /*
     * --------------------------------------------------------------
     * Final response
     * --------------------------------------------------------------
     */
    return response()->json([
        'status'  => $status,
        'message' => $message,

        'data' => [
            /*
             * User
             */
            'user' => $this->user($user),

            /*
             * JWT
             */
            'token' => $this->accessTokenData($user),

            /*
             * Competition
             */
            'competition' => $competition,

            /*
             * Existing competition registration
             */
            'registration' => $registration,
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