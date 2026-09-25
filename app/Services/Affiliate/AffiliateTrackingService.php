<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateClick;
use App\Models\AffiliateLink;
use Illuminate\Http\Request;

class AffiliateTrackingService
{
    public function track(AffiliateLink $link, Request $request): void
    {
        AffiliateClick::create([
            'affiliate_link_id' => $link->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source' => $request->query('source'),
        ]);
    }
}