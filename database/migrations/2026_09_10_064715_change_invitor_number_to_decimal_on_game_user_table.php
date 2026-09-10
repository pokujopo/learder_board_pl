<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->decimal('invitor_number', 30, 0)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->string('invitor_number')
                ->nullable()
                ->change();
        });
    }
};