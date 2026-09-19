<?php

namespace App\Services;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\CompetitionVerification;
use App\Models\Game;
use App\Models\GameUser;
use App\Models\User;
use App\Services\Ranking\RankingService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExistingUserGameJoinService
{
    public function __construct(
        protected RankingService $rankingService,
        protected ReferralService $referralService
    ) {
    }

    public function join(
        User $user,
        Game $game,
        string $phone,
        string $referralCode,
        array $consents
    ): GameUser {
        return DB::transaction(function () use (
            $user,
            $game,
            $phone,
            $referralCode,
            $consents
        ) {
            /*
            |--------------------------------------------------------------------------
            | 1. Competition validation
            |--------------------------------------------------------------------------
            */

            if (!$game->is_active) {
                throw new RuntimeException(
                    'This competition is not accepting joins.'
                );
            }

            if (!($consents['terms'] ?? false)) {
                throw new RuntimeException(
                    'You must accept the terms and conditions.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Phone must belong to authenticated account
            |--------------------------------------------------------------------------
            */

            if (
                $this->normalizePhone($phone)
                !== $this->normalizePhone($user->phone_number)
            ) {
                throw new RuntimeException(
                    'The phone number does not match your account.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. User may join multiple competitions.
            |
            | The only restriction is:
            | same user + same competition = duplicate.
            |--------------------------------------------------------------------------
            */

            $alreadyJoined = GameUser::query()
                ->where('user_id', $user->id)
                ->where('game_id', $game->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyJoined) {
                throw new RuntimeException(
                    'You are already registered for this competition.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Find existing referral verification
            |--------------------------------------------------------------------------
            */

            $normalizedReferralCode =
                ReferralService::normalizeRefercode($referralCode);

            $verification = CompetitionVerification::query()
                ->where('game_id', $game->id)
                ->where('refercode', $normalizedReferralCode)
                ->lockForUpdate()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | 5. Existing verification
            |--------------------------------------------------------------------------
            */

            if ($verification) {
                if ($verification->used_at !== null) {
                    throw new RuntimeException(
                        'This referral code has already been used.'
                    );
                }

                if (
                    $verification->expires_at !== null
                    && $verification->expires_at->isPast()
                ) {
                    throw new RuntimeException(
                        'This referral code has expired.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 6. External referral verification
            |--------------------------------------------------------------------------
            */

            if (!$verification) {
                try {
                    $externalData = $this->referralService->verify(
                        $referralCode,
                        $game
                    );
                } catch (RefercodeNotFoundException $exception) {
                    throw new RuntimeException(
                        'Referral code is invalid for this competition.',
                        previous: $exception
                    );
                } catch (
                    ReferralServiceUnavailableException $exception
                ) {
                    throw new RuntimeException(
                        'Referral service is unavailable.',
                        previous: $exception
                    );
                }

                $verification = CompetitionVerification::create([
                    'game_id' => $game->id,

                    'refercode' => $externalData['refer_code'],

                    'customer_name' =>
                        $externalData['customer_name'],

                    'invitor_number' =>
                        $externalData['invitor_number'],

                    'token_hash' => hash(
                        'sha256',
                        Str::random(64)
                    ),

                    'expires_at' => now()->addMinutes(30),

                    'used_at' => null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Create competition membership
            |--------------------------------------------------------------------------
            |
            | Existing User is reused.
            | No new User record is created.
            |--------------------------------------------------------------------------
            */

            $gameUser = GameUser::create([
                'user_id' => $user->id,

                'game_id' => $game->id,

                'phone_number' => $user->phone_number,

                'refercode' => $verification->refercode,

                'invitor_number' =>
                    $verification->invitor_number,

                'refercode_verified' => true,

                'verified_at' => now(),

                'status' => 'active',

                'current_rank' => 0,

                'previous_rank' => 0,

                'rank_change' => 0,

                'rank_movement' => 'none',
            ]);

            /*
            |--------------------------------------------------------------------------
            | 8. Consume verification
            |--------------------------------------------------------------------------
            */

            $verification->update([
                'used_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 9. Recalculate THIS competition ranking
            |--------------------------------------------------------------------------
            */

            $this->rankingService->recalculate($game);

            $gameUser->refresh();

            /*
            |--------------------------------------------------------------------------
            | 10. Broadcast ranking update
            |--------------------------------------------------------------------------
            */

            event(
                new \App\Events\RankingUpdated($game)
            );

            return $gameUser;
        });
    }

    private function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }
}