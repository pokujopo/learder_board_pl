<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            // Check if the unique index name already exists in MySQL schema
            $indexExists = DB::select("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = SCHEMA() 
                AND TABLE_NAME = 'game_user' 
                AND INDEX_NAME = 'game_user_game_id_refercode_unique'
            ");

            // Only add the unique constraint if it does not exist yet
            if (empty($indexExists)) {
                $table->unique(
                    ['game_id', 'refercode'],
                    'game_user_game_id_refercode_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->dropUnique(
                'game_user_game_id_refercode_unique'
            );
        });
    }
};
