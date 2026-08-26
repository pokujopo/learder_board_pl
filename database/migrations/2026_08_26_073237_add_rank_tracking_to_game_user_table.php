<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->unsignedInteger('current_rank')
                ->nullable()
                ->after('refercode_verified');

            $table->unsignedInteger('previous_rank')
                ->nullable()
                ->after('current_rank');

            $table->unsignedInteger('rank_change')
                ->default(0)
                ->after('previous_rank');

            $table->string('rank_movement')
                ->default('same')
                ->after('rank_change');

            $table->index([
                'game_id',
                'current_rank',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->dropIndex([
                'game_id',
                'current_rank',
            ]);

            $table->dropColumn([
                'current_rank',
                'previous_rank',
                'rank_change',
                'rank_movement',
            ]);
        });
    }
};