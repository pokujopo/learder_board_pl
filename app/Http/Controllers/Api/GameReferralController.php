<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use App\Services\Referral\ReferralService;
use Throwable;

class GameReferralController extends Controller
{
    public function verify(
        Request $request,
        Game $game,
        ReferralService $referralService
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
                'status' => 403,
                'message' => 'This game is currently inactive.',
            ], 403);
        }

        try {
            $result = $referralService->fetchAndSync(
                $validated['refercode'],
                $game
            );

            return response()->json([
                'status' => 200,
                'message' => 'Refercode verified successfully.',
                'game' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'code' => $game->code,
                ],
                'data' => $result['user'],
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'status' => 404,
                'message' => 'Unable to verify refercode.',
            ], 404);
        }
    }
}