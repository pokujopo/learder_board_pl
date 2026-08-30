<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Yasuser;
use Illuminate\Database\Seeder;

class MultiGameReferralTestSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | GAME 1
        |--------------------------------------------------------------------------
        */

        $game1 = Game::findOrFail(1);

        Yasuser::updateOrCreate(
            [
                'game_id' => $game1->id,
                'refercode' => 'GAME1_TEST_001',
            ],
            [
                'compitetor_name' => 'Game 1 User',
                'total_inviter_number' => 1000,
                'last_synced_at' => now(),
            ]
        );

        Yasuser::updateOrCreate(
            [
                'game_id' => $game1->id,
                'refercode' => 'GAME1_TEST_002',
            ],
            [
                'compitetor_name' => 'Game 1 User 2',
                'total_inviter_number' => 2000,
                'last_synced_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | GAME 2
        |--------------------------------------------------------------------------
        */

        $game2 = Game::firstOrCreate(
            [
                'code' => 'voda-gift',
            ],
            [
                'name' => 'VODA Gift',
                'is_active' => true,
            ]
        );

        Yasuser::updateOrCreate(
            [
                'game_id' => $game2->id,
                'refercode' => 'GAME2_TEST_001',
            ],
            [
                'compitetor_name' => 'Voda User',
                'total_inviter_number' => 900000,
                'last_synced_at' => now(),
            ]
        );

        Yasuser::updateOrCreate(
            [
                'game_id' => $game2->id,
                'refercode' => 'GAME2_TEST_002',
            ],
            [
                'compitetor_name' => 'Voda User 2',
                'total_inviter_number' => 800000,
                'last_synced_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | GAME 3
        |--------------------------------------------------------------------------
        */

        $game3 = Game::firstOrCreate(
            [
                'code' => 'jengine-gift',
            ],
            [
                'name' => 'Jengine Gift',
                'is_active' => true,
            ]
        );

        Yasuser::updateOrCreate(
            [
                'game_id' => $game3->id,
                'refercode' => 'GAME3_TEST_001',
            ],
            [
                'compitetor_name' => 'Jengine User',
                'total_inviter_number' => 700000,
                'last_synced_at' => now(),
            ]
        );

        Yasuser::updateOrCreate(
            [
                'game_id' => $game3->id,
                'refercode' => 'GAME3_TEST_002',
            ],
            [
                'compitetor_name' => 'Jengine User 2',
                'total_inviter_number' => 600000,
                'last_synced_at' => now(),
            ]
        );

        $this->command->info('Multi-game referral test data created successfully.');

        $this->command->info(
            "Game 1: {$game1->id} - {$game1->name}"
        );

        $this->command->info(
            "Game 2: {$game2->id} - {$game2->name}"
        );

        $this->command->info(
            "Game 3: {$game3->id} - {$game3->name}"
        );
    }
}