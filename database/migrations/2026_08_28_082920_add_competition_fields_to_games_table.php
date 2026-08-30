<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {

            $table->dateTime('start_date')
                ->nullable()
                ->after('is_active');

            $table->dateTime('end_date')
                ->nullable()
                ->after('start_date');

            $table->decimal('first_place_prize', 15, 2)
                ->nullable()
                ->after('end_date');

            $table->decimal('second_place_prize', 15, 2)
                ->nullable()
                ->after('first_place_prize');

            $table->decimal('third_place_prize', 15, 2)
                ->nullable()
                ->after('second_place_prize');

            $table->text('competition_rules')
                ->nullable()
                ->after('third_place_prize');

            $table->text('winning_instructions')
                ->nullable()
                ->after('competition_rules');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'end_date',
                'first_place_prize',
                'second_place_prize',
                'third_place_prize',
                'competition_rules',
                'winning_instructions',
            ]);
        });
    }
};