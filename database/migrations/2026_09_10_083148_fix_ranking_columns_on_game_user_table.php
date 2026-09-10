<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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