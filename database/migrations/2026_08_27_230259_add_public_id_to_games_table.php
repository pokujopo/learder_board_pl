<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('public_id', 32)->nullable();
        });

        DB::table('games')
            ->whereNull('public_id')
            ->get()
            ->each(function ($game) {
                DB::table('games')
                    ->where('id', $game->id)
                    ->update([
                        'public_id' => 'gm_' . Str::random(24),
                    ]);
            });

        Schema::table('games', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};