<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use Illuminate\Http\Request;
class CompetitionController extends Controller
{

    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $query = Game::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $games = $query
            ->orderBy('start_date')
            ->paginate($perPage);

        $games->through(fn (Game $game) => $this->resource($game));

        return response()->json([
            'status' => 200,
            'data' => $games,
        ]);
    }

   

       private function resource(Game $game): array
    {
        $now = now();

        $status = 'draft';

        if ($game->start_date && $game->end_date) {
            if ($now->lt($game->start_date)) {
                $status = 'upcoming';
            } elseif ($now->gt($game->end_date)) {
                $status = 'completed';
            } elseif ($now->diffInHours($game->end_date, false) <= 24) {
                $status = 'ending_soon';
            } else {
                $status = 'live';
            }
        }

        return [
            'id' => $game->public_id,
            'name' => $game->name,
            'code' => $game->code,
            'status' => $status,
            'is_active' => (bool) $game->is_active,
            'start_at' => $game->start_date?->toISOString(),
            'end_at' => $game->end_date?->toISOString(),
            'participants' => GameUser::query()
                ->where('game_id', $game->id)
                ->where('refercode_verified', true)
                ->count(),
            'prizes' => [
                'first_place_prize' => $game->first_place_prize,
                'second_place_prize' => $game->second_place_prize,
                'third_place_prize' => $game->third_place_prize,
            ],
            'rules' => $game->competition_rules,
            'winning_instructions' => $game->winning_instructions,
        ];
    }    
}
