<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameUser;
use App\Models\CompetitionVerification;
use App\Models\User;
use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Str;
use App\Services\Ranking\RankingService;
use Illuminate\Support\Facades\DB;
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
            if (!$game->is_active) {
                throw new RuntimeException(
                    'This competition is not accepting joins.'
                );
            }

            if (!$consents['terms']) {
                throw new RuntimeException(
                    'You must accept the terms and conditions.'
                );
            }

            /*
             * User mmoja haruhusiwi kujiunga na competition zaidi ya moja.
             */
            $alreadyJoined = GameUser::where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyJoined) {
                throw new RuntimeException(
                    'You have already joined a competition.'
                );
            }

            /*
             * Hakikisha phone inayotumwa ni ya account iliyopo.
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
             * Tafuta referral verification ya competition hii.
             */
          /*
 * Tafuta referral kwenye database kwanza.
 */
$verification = CompetitionVerification::where(
    'game_id',
    $game->id
)
    ->where('refercode', ReferralService::normalizeRefercode($referralCode))
    ->lockForUpdate()
    ->first();

/*
 * Kama referral ipo database, tumia verification iliyopo.
 */
if ($verification) {

    if ($verification->used_at !== null) {
        throw new RuntimeException(
            'This referral code has already been used.'
        );
    }

    if (
        $verification->expires_at !== null &&
        $verification->expires_at->isPast()
    ) {
        throw new RuntimeException(
            'This referral code has expired.'
        );
    }

} else {

    /*
     * Kama referral haipo database,
     * i-verify kupitia external ReferralService.
     */
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

    /*
     * External API ime-confirm referral.
     *
     * Tunatengeneza verification record ili referral
     * iweze kufuatiliwa na kutumika mara moja tu.
     */
    $verification = CompetitionVerification::create([
        'game_id' => $game->id,
        'refercode' => $externalData['refer_code'],
        'customer_name' => $externalData['customer_name'],
        'invitor_number' => $externalData['invitor_number'],

        /*
         * Token hash ya ndani kwa verification record.
         * Referral yenyewe imethibitishwa na external API.
         */
        'token_hash' => hash(
            'sha256',
            Str::random(64)
        ),

        'expires_at' => now()->addMinutes(30),
        'used_at' => null,
    ]);
}

            /*
             * Hakikisha referral haijatumika.
             */
            

            /*
             * Tengeneza GameUser kwa existing user.
             * Hatumtengenezi User mpya.
             */
            $gameUser = GameUser::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'phone_number' => $user->phone_number,

            'refercode' => $verification->refercode,
            'invitor_number' => $verification->invitor_number,

            'refercode_verified' => true,
            'status' => 'active',

            'current_rank' => 0,
            'previous_rank' => 0,
            'rank_change' => 0,
            'rank_movement' => 'none',
]);

            /*
             * Mark referral kuwa imetumika.
             */
        $verification->update([
            'used_at' => now(),
        ]);

        $this->rankingService->recalculate($game);

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