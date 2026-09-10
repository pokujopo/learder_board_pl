<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('refercode');

            // String kwa sababu invitor_number inaweza kuwa kubwa kuliko INT ya MySQL
            $table->string('invitor_number')->nullable()->after('customer_name');

            $table->timestamp('last_synced_at')
                ->nullable()
                ->after('verified_at');

            $table->string('status')
                ->default('active')
                ->after('last_synced_at');

            $table->index([
                'game_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->dropIndex([
                'game_id',
                'status',
            ]);

            $table->dropColumn([
                'customer_name',
                'invitor_number',
                'last_synced_at',
                'status',
            ]);
        });
    }
};