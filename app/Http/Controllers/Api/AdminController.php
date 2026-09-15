<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function competitions(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return response()->json([
            'status' => 200,
            'data' => [
                'competitions' => Game::latest()->paginate($perPage),
            ],
        ]);
    }

    public function storeCompetition(Request $request)
    {
        $validated = $request->validate($this->competitionRules());

        $game = Game::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'is_active' => $validated['is_active'] ?? true,
            'external_api_base_url' => rtrim($validated['external_api_base_url'], '/'),
            'start_date' => $validated['start_at'],
            'end_date' => $validated['end_at'],
            'first_place_prize' => $validated['first_prize'],
            'second_place_prize' => $validated['second_prize'],
            'third_place_prize' => $validated['third_prize'],
            'competition_rules' => $validated['competition_rules'],
            'winning_instructions' => $validated['winning_instructions'],
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Competition created successfully.',
            'data' => ['competition' => $game],
        ], 201);
    }

    public function showCompetition(Game $game)
    {
        return response()->json([
            'status' => 200,
            'data' => ['competition' => $game],
        ]);
    }

    public function updateCompetition(Request $request, Game $game)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:100', 'alpha_dash', 'unique:games,code,' . $game->id],
            'is_active' => ['sometimes', 'boolean'],
            'external_api_base_url' => ['sometimes', 'url', 'max:2048'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'first_prize' => ['sometimes', 'numeric', 'min:0'],
            'second_prize' => ['sometimes', 'numeric', 'min:0'],
            'third_prize' => ['sometimes', 'numeric', 'min:0'],
            'competition_rules' => ['sometimes', 'string'],
            'winning_instructions' => ['sometimes', 'string'],
        ]);

        $map = [
            'name' => 'name',
            'code' => 'code',
            'is_active' => 'is_active',
            'external_api_base_url' => 'external_api_base_url',
            'start_at' => 'start_date',
            'end_at' => 'end_date',
            'first_prize' => 'first_place_prize',
            'second_prize' => 'second_place_prize',
            'third_prize' => 'third_place_prize',
            'competition_rules' => 'competition_rules',
            'winning_instructions' => 'winning_instructions',
        ];

        foreach ($map as $from => $to) {
            if (array_key_exists($from, $validated)) {
                $game->{$to} = $from === 'external_api_base_url'
                    ? rtrim($validated[$from], '/')
                    : $validated[$from];
            }
        }

        $game->save();

        return response()->json([
            'status' => 200,
            'message' => 'Competition updated successfully.',
            'data' => ['competition' => $game->fresh()],
        ]);
    }

    public function destroyCompetition(Game $game)
    {
        $game->update(['is_active' => false]);

        return response()->json([
            'status' => 200,
            'message' => 'Competition deactivated successfully.',
        ]);
    }

    private function competitionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:games,code'],
            'is_active' => ['sometimes', 'boolean'],
            'external_api_base_url' => ['required', 'url', 'max:2048'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'first_prize' => ['required', 'numeric', 'min:0'],
            'second_prize' => ['required', 'numeric', 'min:0'],
            'third_prize' => ['required', 'numeric', 'min:0'],
            'competition_rules' => ['required', 'string'],
            'winning_instructions' => ['required', 'string'],
        ];
    }
}
