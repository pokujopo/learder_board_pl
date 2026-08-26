<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('game_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->index([
                'game_id',
                'company_id',
                'refercode',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {

            $table->dropForeign(['company_id']);

            $table->dropIndex(
                'yasuser_game_id_company_id_refercode_index'
            );

            $table->dropColumn('company_id');
        });
    }
};