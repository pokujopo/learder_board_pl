<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Services\Affiliate\AffiliateTrackingService;
use Illuminate\Http\Request;

class AffiliateRedirectController extends Controller
{
    public function __invoke(
        string $code,
        Request $request,
        AffiliateTrackingService $trackingService
    ) {
        $link = AffiliateLink::where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $trackingService->track($link, $request);

        return redirect('https://pawacode.com');
    }
}