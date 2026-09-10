<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Ranking\RankingService;
use Illuminate\Console\Command;

class RecalculateRankings extends Command
{
    protected $signature = 'rankings:recalculate';

    protected $description = 'Recalculate rankings for active competitions';

    public function handle(
        RankingService $rankingService
    ): int {
        $games = Game::query()
            ->where('is_active', true)
            ->get();

        foreach ($games as $game) {

            $result = $rankingService->recalculate($game);

            cache()->forget(
                'game:' . $game->id . ':ranking'
            );

            $this->info(
                "Game {$game->id}: {$result['participants']} participants ranked."
            );
        }

        return self::SUCCESS;
    }
}