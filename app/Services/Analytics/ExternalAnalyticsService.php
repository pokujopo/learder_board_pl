<?php

namespace App\Services\Analytics;

use App\Models\Game;
use App\Models\GameUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ExternalAnalyticsService
{
    public function getForUser(Game $game, int $userId): array
    {
        $participant = GameUser::query()
            ->where('game_id', $game->id)
            ->where('user_id', $userId)
            ->where('refercode_verified', true)
            ->where('status', 'active')
            ->first();

        if (!$participant) {
            throw (new ModelNotFoundException)
                ->setModel(GameUser::class);
        }

        $invites = (int) ($participant->invitor_number ?? 0);

        return [
            'competition' => [
                'public_id' => $game->public_id,
                'name' => $game->name,
            ],

            'analytics' => [
                'invites' => $invites,
                'points_earned' => $invites * 10,
                'current_rank' => $participant->current_rank,
                'previous_rank' => $participant->previous_rank,
                'rank_change' => $participant->rank_change,
                'rank_movement' => $participant->rank_movement,
            ],
        ];
    }
}