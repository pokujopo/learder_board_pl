<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateLink;
use Illuminate\Support\Carbon;

class AffiliateAnalyticsService
{
    public function getStats(
        AffiliateLink $link,
        string $timeframe = '7d'
    ): array {
        $timeframe = in_array($timeframe, ['7d', '30d', 'all'], true)
            ? $timeframe
            : '7d';

        $clicks = $link->clicks();

        $filteredClicks = clone $clicks;

        $periodStart = null;

        if ($timeframe === '7d') {
            $periodStart = Carbon::now()
                ->subDays(6)
                ->startOfDay();

            $filteredClicks->where(
                'created_at',
                '>=',
                $periodStart
            );
        }

        if ($timeframe === '30d') {
            $periodStart = Carbon::now()
                ->subDays(29)
                ->startOfDay();

            $filteredClicks->where(
                'created_at',
                '>=',
                $periodStart
            );
        }

        $totalClicks = (clone $filteredClicks)->count();

        $referrals = $link->referrals()
            ->whereNotNull('verified_at');

        if ($periodStart) {
            $referrals->where(
                'verified_at',
                '>=',
                $periodStart
            );
        }

        $verifiedReferrals = $referrals->count();

        $rewards = $link->rewards();

        if ($periodStart) {
            $rewards->where(
                'affiliate_rewards.created_at',
                '>=',
                $periodStart
            );
        }

        $pointsEarned = (int) $rewards->sum('points');

        $conversionRate = $totalClicks > 0
            ? round(
                ($verifiedReferrals / $totalClicks) * 100,
                2
            )
            : 0;

        $trafficSources = (clone $filteredClicks)
            ->selectRaw(
                "COALESCE(NULLIF(source, ''), 'direct') as source,
                 COUNT(*) as clicks"
            )
            ->groupBy('source')
            ->orderByDesc('clicks')
            ->get()
            ->map(function ($row) use ($totalClicks) {
                return [
                    'source' => $row->source,
                    'clicks' => (int) $row->clicks,
                    'percentage' => $totalClicks > 0
                        ? round(
                            ($row->clicks / $totalClicks) * 100,
                            2
                        )
                        : 0,
                ];
            })
            ->values();

        $chartData = (clone $filteredClicks)
            ->selectRaw(
                'DATE(created_at) as date,
                 COUNT(*) as clicks'
            )
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'clicks' => (int) $row->clicks,
            ])
            ->values();

        return [
            'timeframe' => $timeframe,

            'total_clicks' => $totalClicks,

            'unique_visitors' => (clone $filteredClicks)
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address'),

            'verified_referrals' => $verifiedReferrals,

            'conversion_rate' => $conversionRate,

            'points_earned' => $pointsEarned,

            'clicks_today' => (clone $clicks)
                ->whereDate(
                    'created_at',
                    Carbon::today()
                )
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

            'traffic_sources' => $trafficSources,

            'chart' => $chartData,
        ];
    }
}