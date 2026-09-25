<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateLink;
use Illuminate\Support\Carbon;

class AffiliateAnalyticsService
{
    public function getStats(AffiliateLink $link): array
    {
        $clicks = $link->clicks();

        return [
            'total_clicks' => (clone $clicks)->count(),

            'unique_visitors' => (clone $clicks)
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address'),

            'clicks_today' => (clone $clicks)
                ->whereDate('created_at', Carbon::today())
                ->count(),

            'clicks_this_week' => (clone $clicks)
                ->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ])
                ->count(),

            'clicks_this_month' => (clone $clicks)
                ->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])
                ->count(),
        ];
    }
}