<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\Ranking\RankingService;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function __construct(
        private RankingService $ranking,
    ) {
    }

    /**
     * GET /api/v1/competitions/{game}/ranking
     *
     * Example:
     * /api/v1/competitions/gm_xxx/ranking?page=1&per_page=20
     */
    public function index(Request $request, Game $game)
{
    $page = (int) $request->integer('page', 1);

    $perPage = (int) $request->integer('per_page', 20);

    $participants = $this->ranking->getRanking(
        $game,
        $page,
        $perPage
    );

    $currentUserId = $request->user()?->id;

    return response()->json([
        'status' => 200,

        'data' => [

            'competition' => [
                'public_id' => $game->public_id,
                'name' => $game->name,
                'status' => $this->competitionStatus($game),
            ],

            'rankings' => collect($participants->items())
                ->map(function ($participant) use ($currentUserId) {

                    return [
                        'rank' => $participant['rank'],

                        'user' => $participant['user'],

                        'refercode' =>
                            $participant['refercode'],

                        'invitor_number' =>
                            $participant['invitor_number'],

                        'previous_rank' =>
                            $participant['previous_rank'],

                        'rank_change' =>
                            $participant['rank_change'],

                        'rank_movement' =>
                            $participant['rank_movement'],

                        'label' =>
                            $currentUserId !== null &&
                            $participant['user']['id'] === $currentUserId
                                ? 'you'
                                : null,
                    ];
                })
                ->values(),

            'pagination' => [
                'current_page' =>
                    $participants->currentPage(),

                'per_page' =>
                    $participants->perPage(),

                'total' =>
                    $participants->total(),

                'last_page' =>
                    $participants->lastPage(),

                'from' =>
                    $participants->firstItem(),

                'to' =>
                    $participants->lastItem(),

                'has_more' =>
                    $participants->hasMorePages(),
            ],
        ],
    ]);
}

    private function competitionStatus(Game $game): string
    {
        $now = now();

        if (!$game->start_date || !$game->end_date) {
            return 'draft';
        }

        if ($now->lt($game->start_date)) {
            return 'upcoming';
        }

        if ($now->gt($game->end_date)) {
            return 'completed';
        }

        return 'live';
    }
}