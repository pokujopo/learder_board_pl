<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {
            $table->foreignId('game_id')
                ->nullable()
                ->after('id')
                ->constrained('games')
                ->cascadeOnDelete();

            $table->index([
                'game_id',
                'total_inviter_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropIndex([
                'yasuser_game_id_total_inviter_number_index',
            ]);
            $table->dropColumn('game_id');
        });
    }
};