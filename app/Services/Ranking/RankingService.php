<?php

namespace App\Services\Ranking;

use App\Models\GameUser;
use Illuminate\Support\Collection;

class RankingService
{
    public function getRanking(int $gameId): Collection
    {
        $users = GameUser::query()
            ->where('game_id', $gameId)
            ->where('refercode_verified', true)
            ->with('yasuser')
            ->paginate(30);

        return $users
            ->filter(fn ($gameUser) => $gameUser->yasuser !== null)
            ->sortByDesc(
                fn ($gameUser) =>
                    $gameUser->yasuser->total_inviter_number
            )
            ->values();
    }

    public function updateRanks(int $gameId): Collection
    {
        $users = $this->getRanking($gameId);

        $users->each(function (
            GameUser $gameUser,
            int $index
        ) {

            $newRank = $index + 1;
            $oldRank = $gameUser->current_rank;

            if ($oldRank === null) {
                $previousRank = null;
                $rankChange = 0;
                $movement = 'new';

            } else {

                $previousRank = $oldRank;

                if ($newRank < $oldRank) {
                    $rankChange = $oldRank - $newRank;
                    $movement = 'up';

                } elseif ($newRank > $oldRank) {
                    $rankChange = $newRank - $oldRank;
                    $movement = 'down';

                } else {
                    $rankChange = 0;
                    $movement = 'same';
                }
            }

            $gameUser->update([
                'previous_rank' => $previousRank,
                'current_rank' => $newRank,
                'rank_change' => $rankChange,
                'rank_movement' => $movement,
            ]);
        });

        return $this->getRanking($gameId);
    }
}