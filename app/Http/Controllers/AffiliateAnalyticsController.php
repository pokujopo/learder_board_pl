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
    $user = $request->user();

    $link = AffiliateLink::firstOrCreate(
        ['user_id' => $user->id],
        ['code' => strtoupper(\Illuminate\Support\Str::random(8))]
    );

    return response()->json([
        'success' => true,
        'data' => [
            'link' => [
                'code' => $link->code,
                'url' => 'https://pawacode.com/ref/' . $link->code,
            ],
            'stats' => $analyticsService->getStats($link),
        ],
    ]);
}
}