<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function data(Request $request)
    {
        $user = $request->user();

        $gameUsers = $user->gameUsers()
            ->with('game')
            ->get();

        $games = $gameUsers->map(function ($gameUser) {

            $yasuser = \App\Models\Yasuser::query()
                ->where('game_id', $gameUser->game_id)
                ->where('refercode', $gameUser->refercode)
                ->first();

            return [
                'game' => [
                    'id' => $gameUser->game->id,
                    'name' => $gameUser->game->name,
                    'code' => $gameUser->game->code,
                ],

                'refercode' => $gameUser->refercode,

                'refercode_verified' =>
                    (bool) $gameUser->refercode_verified,

                'invitor_number' =>
                    $yasuser?->total_inviter_number ?? 0,

                'ranking' => [
                    'current_rank' =>
                        $gameUser->current_rank,

                    'previous_rank' =>
                        $gameUser->previous_rank,

                    'rank_change' =>
                        $gameUser->rank_change,

                    'rank_movement' =>
                        $gameUser->rank_movement,
                ],

                'joined_at' =>
                    $gameUser->created_at,
            ];
        });

        return response()->json([
            'status' => 200,

            'user' => [
                'name' => $user->name,
                'email' => $user->email,

                // Zitaongezwa kwenye users table baadaye
                'phone' => $user->phone ?? null,
                'location' => $user->location ?? null,

                'games_joined' => $gameUsers->count(),

                'games' => $games->values(),
            ],
        ]);
    }
}