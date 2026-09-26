<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Services\Affiliate\AffiliateReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateReferralController extends Controller
{
    public function __invoke(
        Request $request,
        AffiliateReferralService $referralService
    ): JsonResponse {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $link = AffiliateLink::where('code', strtoupper(trim($validated['code'])))
            ->where('is_active', true)
            ->first();

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid affiliate code.',
            ], 422);
        }

        if ($link->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot verify your own affiliate code.',
            ], 422);
        }

        $referral = $referralService->verify(
            $link,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Affiliate code verified successfully.',
            'data' => [
                'referral_id' => $referral->id,
                'verified_at' => $referral->verified_at,
            ],
        ]);
    }
}