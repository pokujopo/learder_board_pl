<?php

namespace App\Services\Ranking;

use App\Models\GameUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RankingService
{
    public function getRanking(int $gameId, int $limit = 30): Collection
    {
        return GameUser::query()
            ->where('game_user.game_id', $gameId)
            ->where('game_user.refercode_verified', true)
            ->leftJoin('yasuser', function ($join) {
                $join->on('yasuser.game_id', '=', 'game_user.game_id')
                    ->on('yasuser.refercode', '=', 'game_user.refercode');
            })
            ->select([
                'game_user.*',
                'yasuser.compitetor_name as referral_name',
                'yasuser.total_inviter_number as referral_score',
            ])
            ->with('user')
            ->orderByDesc(DB::raw('COALESCE(yasuser.total_inviter_number, 0)'))
            ->orderBy('game_user.id')
            ->limit(min(max($limit, 1), 10000))
            ->get()
            ->values();
    }

    public function updateRanks(int $gameId): Collection
    {
        $users = $this->getRanking($gameId, 10000);

        DB::transaction(function () use ($users) {
            $users->each(function (GameUser $participant, int $index) {
                $newRank = $index + 1;
                $oldRank = $participant->current_rank;

                $movement = $oldRank === null
                    ? 'new'
                    : ($newRank < $oldRank
                        ? 'up'
                        : ($newRank > $oldRank ? 'down' : 'same'));

                $participant->update([
                    'previous_rank' => $oldRank,
                    'current_rank' => $newRank,
                    'rank_change' => $oldRank === null
                        ? 0
                        : abs($oldRank - $newRank),
                    'rank_movement' => $movement,
                ]);
            });
        });

        return $this->getRanking($gameId, 100);
    }
}
