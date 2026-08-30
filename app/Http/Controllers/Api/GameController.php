<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;

class GameController extends Controller
{
    public function index()
    {
        $now = now();

        $games = Game::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->withCount([
                'users as participants_count',

                'users as verified_refercodes_count' => function ($query) {
                    $query->wherePivot(
                        'refercode_verified',
                        true
                    );
                },
            ])
            ->orderBy('start_date')
            ->get()
            ->map(function (Game $game) use ($now) {

                $startDate = $game->start_date;
                $endDate = $game->end_date;

                if ($now->lt($startDate)) {

                    $status = 'upcoming';
                    $statusLabel = 'Starts Soon';

                } elseif ($now->gte($startDate) &&
                          $now->lte($endDate)) {

                    /*
                    |--------------------------------------------------------------------------
                    | Ending soon
                    |--------------------------------------------------------------------------
                    */

                    if ($now->diffInHours($endDate, false) <= 24) {

                        $status = 'ending_soon';
                        $statusLabel = 'Ending Soon';

                    } else {

                        $status = 'live';
                        $statusLabel = 'Live Now';
                    }

                } else {

                    $status = 'completed';
                    $statusLabel = 'Complete';
                }

                return [
                    'id' => $game->public_id,

                    'name' => $game->name,

                    'status' => $status,

                    'status_label' => $statusLabel,

                    'start_date' =>
                        $startDate->toISOString(),

                    'end_date' =>
                        $endDate->toISOString(),

                    'participants' =>
                        $game->participants_count,

                    'verified_refercodes' =>
                        $game->verified_refercodes_count,

                    'prizes' => [
                        'first_place_prize' =>
                            $game->first_place_prize,

                        'second_place_prize' =>
                            $game->second_place_prize,

                        'third_place_prize' =>
                            $game->third_place_prize,
                    ],

                    'rules' =>
                        $game->competition_rules,
                ];
            });

        return response()->json([
            'status' => 200,

            'message' =>
                'Games retrieved successfully.',

            'data' => $games,
        ]);
    }
}