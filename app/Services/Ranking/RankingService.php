<?php

namespace App\Services\Ranking;

use App\Models\Game;
use App\Models\GameUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingService
{
    private const CACHE_TTL = 300;

    private function rankingCacheKey(Game $game): string
    {
        return "competition:{$game->id}:ranking";
    }

    /**
     * Recalculate ranking and movement.
     *
     * DB = source of truth.
     * Redis = read cache.
     */
    public function recalculate(Game $game): array
    {
        $result = DB::transaction(function () use ($game) {

            $participants = GameUser::query()
                ->where('game_id', $game->id)
                ->where('refercode_verified', true)
                ->where('status', 'active')
                ->whereNotNull('invitor_number')
                ->orderByRaw('CAST(invitor_number AS UNSIGNED) DESC')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $updated = 0;
            $movements = [
                'up' => 0,
                'down' => 0,
                'none' => 0,
            ];

            foreach ($participants as $index => $participant) {

                $newRank = $index + 1;
                $oldRank = (int) $participant->current_rank;

                /*
                 * First ranking.
                 */
                if ($oldRank === 0) {

                    $participant->previous_rank = 0;
                    $participant->current_rank = $newRank;
                    $participant->rank_change = 0;
                    $participant->rank_movement = 'none';

                    $participant->save();

                    $movements['none']++;
                    $updated++;

                    continue;
                }

                /*
                 * Save previous rank.
                 */
                $participant->previous_rank = $oldRank;

                /*
                 * Set new rank.
                 */
                $participant->current_rank = $newRank;

                /*
                 * Positive = moved UP.
                 * Negative = moved DOWN.
                 */
                $rankChange = $oldRank - $newRank;

                $participant->rank_change = $rankChange;

                if ($rankChange > 0) {

                    $participant->rank_movement = 'up';
                    $movements['up']++;

                } elseif ($rankChange < 0) {

                    $participant->rank_movement = 'down';
                    $movements['down']++;

                } else {

                    $participant->rank_movement = 'none';
                    $movements['none']++;
                }

                $participant->save();

                $updated++;
            }

            return [
                'participants' => $participants->count(),
                'updated' => $updated,
                'movements' => $movements,
            ];
        });

        /*
         * IMPORTANT:
         *
         * Only invalidate Redis after DB transaction succeeds.
         */
        $this->forgetRankingCache($game);

        return $result;
    }

    /**
     * Get paginated ranking.
     *
     * Redis contains only plain PHP arrays.
     */
    public function getRanking(
        Game $game,
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {

        $page = max(1, $page);

        $perPage = min(
            max(1, $perPage),
            100
        );

        $ranking = Cache::store('redis')->remember(
            $this->rankingCacheKey($game),
            now()->addSeconds(self::CACHE_TTL),
            function () use ($game) {

                return GameUser::query()
                    ->with([
                        'user:id,name,email,phone_number',
                    ])
                    ->where('game_id', $game->id)
                    ->where('refercode_verified', true)
                    ->where('status', 'active')
                    ->where('current_rank', '>', 0)
                    ->orderBy('current_rank')
                    ->get()
                    ->map(function (GameUser $participant) {

                        return [
                            'rank' => (int) $participant->current_rank,

                            'user' => [
                                'id' => (int) $participant->user_id,
                                'name' => $participant->user?->name,
                            ],

                            'refercode' => $participant->refercode,

                            'invitor_number' =>
                                $participant->invitor_number,

                            'previous_rank' =>
                                (int) $participant->previous_rank,

                            'rank_change' =>
                                (int) $participant->rank_change,

                            'rank_movement' =>
                                $participant->rank_movement,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        /*
         * Redis must return normal PHP data.
         */
        $ranking = collect($ranking);

        $total = $ranking->count();

        $items = $ranking
            ->forPage($page, $perPage)
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Get participant ranking.
     */
    public function getParticipantRanking(
        Game $game,
        int $userId
    ): ?array {

        $ranking = Cache::store('redis')->remember(
            $this->rankingCacheKey($game),
            now()->addSeconds(self::CACHE_TTL),
            function () use ($game) {

                return GameUser::query()
                    ->with([
                        'user:id,name,email,phone_number',
                    ])
                    ->where('game_id', $game->id)
                    ->where('refercode_verified', true)
                    ->where('status', 'active')
                    ->where('current_rank', '>', 0)
                    ->orderBy('current_rank')
                    ->get()
                    ->map(function (GameUser $participant) {

                        return [
                            'rank' =>
                                (int) $participant->current_rank,

                            'user' => [
                                'id' =>
                                    (int) $participant->user_id,

                                'name' =>
                                    $participant->user?->name,
                            ],

                            'refercode' =>
                                $participant->refercode,

                            'invitor_number' =>
                                $participant->invitor_number,

                            'previous_rank' =>
                                (int) $participant->previous_rank,

                            'rank_change' =>
                                (int) $participant->rank_change,

                            'rank_movement' =>
                                $participant->rank_movement,

                            'user_id' =>
                                (int) $participant->user_id,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return collect($ranking)
            ->firstWhere('user_id', $userId);
    }

    /**
     * Forget ranking cache.
     */
    public function forgetRankingCache(Game $game): void
    {
        Cache::store('redis')->forget(
            $this->rankingCacheKey($game)
        );
    }
}