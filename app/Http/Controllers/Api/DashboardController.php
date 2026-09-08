<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameUser;
use App\Models\Yasuser;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $participants = $user->gameUsers()
            ->with('game')
            ->where('refercode_verified', true)
            ->latest()
            ->get();

        $referrals = Yasuser::query()
            ->whereIn('game_id', $participants->pluck('game_id')->unique())
            ->whereIn('refercode', $participants->pluck('refercode')->filter()->unique())
            ->get()
            ->keyBy(fn (Yasuser $referral) => $referral->game_id . ':' . $referral->refercode);

        return response()->json([
            'status' => 200,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'competitions' => $participants
                    ->map(function (GameUser $participant) use ($referrals) {
                        $referral = $referrals->get(
                            $participant->game_id . ':' . $participant->refercode
                        );

                        return [
                            'competition' => [
                                'id' => $participant->game->public_id,
                                'name' => $participant->game->name,
                            ],
                            'referral_code' => $participant->refercode,
                            'score' => (int) ($referral?->total_inviter_number ?? 0),
                            'rank' => $participant->current_rank,
                            'joined_at' => $participant->created_at,
                        ];
                    })
                    ->values(),
            ],
        ]);
    }
}
