<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\ExistingUserGameJoinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class ExistingUserGameJoinController extends Controller
{
    public function __construct(
        protected ExistingUserGameJoinService $joinService
    ) {
    }

    public function store(Request $request, Game $game)
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
            ],

            'referral_code' => [
                'required',
                'string',
                'max:255',
            ],

            'consents' => [
                'required',
                'array',
            ],

            'consents.terms' => [
                'required',
                'boolean',
                'accepted',
            ],

            'consents.sms' => [
                'sometimes',
                'boolean',
            ],

            'consents.future_competitions' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $gameUser = $this->joinService->join(
                user: $request->user(),
                game: $game,
                phone: $request->input('phone'),
                referralCode: $request->input('referral_code'),
                consents: $request->input('consents')
            );

            return response()->json([
                'message' => 'Competition joined successfully.',
                'participation' => [
                    'id' => $gameUser->id,
                    'game_id' => $gameUser->game_id,
                    'verified' => (bool) $gameUser->refercode_verified,
                    'joined_at' => $gameUser->created_at,
                ],
            ], 201);

        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);

        } catch (Throwable $exception) {
    report($exception);

    return response()->json([
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ], 500);
}
    }
}