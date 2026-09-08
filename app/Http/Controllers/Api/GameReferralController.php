<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RefercodeAlreadyUsedException;
use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameReferralController extends Controller
{
    public function verify(
        Request $request,
        Game $game,
        ReferralService $referralService,
    ) {
        $validated = $request->validate([
            'refercode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
        ]);

        if (!$game->is_active) {
            return response()->json([
                'status' => 409,
                'message' => 'This competition is inactive.',
            ], 409);
        }

        $refercode = ReferralService::normalizeRefercode($validated['refercode']);

        if (GameUser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $refercode)
            ->where('refercode_verified', true)
            ->exists()) {
            return response()->json([
                'status' => 409,
                'message' => 'This referral code has already been used in this competition.',
            ], 409);
        }

        try {
            $customer = $referralService->verify($refercode, $game);

            return response()->json([
                'status' => 200,
                'message' => 'Referral code verified successfully.',
                'data' => [
                    'refercode' => $customer['refer_code'],
                    'customer_name' => $customer['customer_name'],
                    'invitor_number' => $customer['invitor_number'],
                ],
            ]);
        } catch (RefercodeAlreadyUsedException $e) {
            return response()->json([
                'status' => 409,
                'message' => $e->getMessage(),
            ], 409);
        } catch (RefercodeNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'This referral code is not recognized.',
            ], 404);
        } catch (ReferralServiceUnavailableException $e) {
            Log::error('Referral verification service unavailable', [
                'game_id' => $game->id,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 503,
                'message' => 'Unable to verify referral code at this time.',
            ], 503);
        } catch (Throwable $e) {
            Log::error('Unexpected referral verification error', [
                'game_id' => $game->id,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to verify referral code.',
            ], 500);
        }
    }
}
