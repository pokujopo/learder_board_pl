<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Normalize existing ranking data
        |--------------------------------------------------------------------------
        */

        DB::statement("
            UPDATE game_user
            SET current_rank = 0
            WHERE current_rank IS NULL
               OR TRIM(current_rank) = ''
               OR current_rank NOT REGEXP '^[0-9]+$'
        ");

        DB::statement("
            UPDATE game_user
            SET previous_rank = 0
            WHERE previous_rank IS NULL
               OR TRIM(previous_rank) = ''
               OR previous_rank NOT REGEXP '^[0-9]+$'
        ");

        DB::statement("
            UPDATE game_user
            SET rank_change = 0
            WHERE rank_change IS NULL
               OR TRIM(rank_change) = ''
               OR rank_change NOT REGEXP '^-?[0-9]+$'
        ");

        DB::statement("
            UPDATE game_user
            SET rank_movement = 'none'
            WHERE rank_movement IS NULL
               OR TRIM(rank_movement) = ''
        ");

        /*
        |--------------------------------------------------------------------------
        | 2. Convert columns to correct types
        |--------------------------------------------------------------------------
        */

        Schema::table('game_user', function (Blueprint $table) {
            $table->integer('current_rank')
                ->default(0)
                ->change();

            $table->integer('previous_rank')
                ->default(0)
                ->change();

            $table->integer('rank_change')
                ->default(0)
                ->change();

            $table->string('rank_movement')
                ->default('none')
                ->change();
        });
    }

    public function down(): void
    {
        //
    }
};
