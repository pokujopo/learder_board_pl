<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AdminGameController extends Controller
{
    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Admin authorization
        |--------------------------------------------------------------------------
        */

        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Only admin users can create games.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:games,code',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'external_api_base_url' => [
                'required',
                'url',
                'max:2048',
            ],

            'start_at' => [
                'required',
                'date',
            ],

            'end_at' => [
                'required',
                'date',
                'after:start_at',
            ],

            'first_prize' => [
                'required',
                'numeric',
                'min:0',
            ],

            'second_prize' => [
                'required',
                'numeric',
                'min:0',
            ],

            'third_prize' => [
                'required',
                'numeric',
                'min:0',
            ],

            'competition_rules' => [
                'required',
                'string',
            ],

            'winning_instructions' => [
                'required',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create game
        |--------------------------------------------------------------------------
        */

        try {

            $game = DB::transaction(function () use ($validated) {

                return Game::create([
                    'name' => $validated['name'],

                    'code' => $validated['code'],

                    'is_active' =>
                        $validated['is_active'] ?? true,

                    'external_api_base_url' =>
                        $validated['external_api_base_url'],

                    'start_date' =>
                        $validated['start_at'],

                    'end_date' =>
                        $validated['end_at'],

                    'first_place_prize' =>
                        $validated['first_prize'],

                    'second_place_prize' =>
                        $validated['second_prize'],

                    'third_place_prize' =>
                        $validated['third_prize'],

                    'competition_rules' =>
                        $validated['competition_rules'],

                    'winning_instructions' =>
                        $validated['winning_instructions'],
                ]);
            });

            return response()->json([
                'status' => 201,
                'message' => 'Game created successfully.',

                'game' => [
                    'public_id' => $game->public_id,
                    'name' => $game->name,
                    'code' => $game->code,
                    'is_active' => $game->is_active,

                    'external_api_base_url' =>
                        $game->external_api_base_url,

                    'start_date' => $game->start_date,
                    'end_date' => $game->end_date,

                    'prizes' => [
                        'first_place' => $game->first_place_prize,
                        'second_place' => $game->second_place_prize,
                        'third_place' => $game->third_place_prize,
                    ],

                    'competition_rules' =>
                        $game->competition_rules,

                    'winning_instructions' =>
                        $game->winning_instructions,
                ],
            ], 201);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to create game.',
            ], 500);
        }
    }
}