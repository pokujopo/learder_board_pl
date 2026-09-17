<?php

namespace App\Events;

use App\Models\Game;
use App\Services\Ranking\RankingService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RankingUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Game $game,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                "competition.{$this->game->public_id}.ranking"
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ranking.updated';
    }

    public function broadcastWith(): array
    {
        $rankingService = app(RankingService::class);

        $ranking = $rankingService->getRanking(
            $this->game,
            1,
            config('ranking.broadcast_per_page', 50)
        );

        return [
            'game' => [
                'public_id' => $this->game->public_id,
                'name' => $this->game->name,
            ],

            'rankings' => collect($ranking->items())
                ->values()
                ->all(),

            'pagination' => [
                'current_page' => $ranking->currentPage(),
                'per_page' => $ranking->perPage(),
                'total' => $ranking->total(),
                'last_page' => $ranking->lastPage(),
            ],
        ];
    }
}