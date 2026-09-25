<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateLink;
use App\Models\User;
use Illuminate\Support\Str;

class AffiliateLinkService
{
    public function create(User $user): AffiliateLink
    {
        return AffiliateLink::create([
            'user_id' => $user->id,
            'code' => $this->generateUniqueCode(),
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (AffiliateLink::where('code', $code)->exists());

        return $code;
    }
}