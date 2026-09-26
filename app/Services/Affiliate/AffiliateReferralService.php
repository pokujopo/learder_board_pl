<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateLink;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReward;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AffiliateReferralService
{
    private const REWARD_POINTS = 100;

    public function verify(
        AffiliateLink $link,
        User $referredUser
    ): AffiliateReferral {
        return DB::transaction(function () use ($link, $referredUser) {
            $referral = AffiliateReferral::firstOrCreate(
                [
                    'affiliate_link_id' => $link->id,
                    'referred_user_id' => $referredUser->id,
                ],
                [
                    'verified_at' => Carbon::now(),
                ]
            );

            AffiliateReward::firstOrCreate(
                [
                    'affiliate_referral_id' => $referral->id,
                ],
                [
                    'user_id' => $link->user_id,
                    'points' => self::REWARD_POINTS,
                    'reason' => 'Verified affiliate referral',
                ]
            );

            return $referral;
        });
    }
}