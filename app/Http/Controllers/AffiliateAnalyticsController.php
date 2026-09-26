<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Services\Affiliate\AffiliateAnalyticsService;
use App\Services\Affiliate\AffiliateLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateAnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        AffiliateAnalyticsService $analyticsService,
        AffiliateLinkService $linkService
    ): JsonResponse {
        $user = $request->user();

        $link = AffiliateLink::where(
            'user_id',
            $user->id
        )->first();

        if (!$link) {
            $link = $linkService->create($user);
        }

        return response()->json([
            'success' => true,

            'data' => [
                'link' => [
                    'code' => $link->code,
                    'url' => 'https://pawacode.com/ref/' . $link->code,
                    'is_active' => $link->is_active,
                ],

                'stats' => $analyticsService->getStats(
                    $link,
                    $request->query('timeframe', '7d')
                ),
            ],
        ]);
    }
}