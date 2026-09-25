<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Services\Affiliate\AffiliateAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateAnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        AffiliateAnalyticsService $analyticsService
    ): JsonResponse {
        $link = AffiliateLink::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'link' => [
                    'code' => $link->code,
                    'url' => url('/api/ref/' . $link->code),
                ],
                'stats' => $analyticsService->getStats($link),
            ],
        ]);
    }
}