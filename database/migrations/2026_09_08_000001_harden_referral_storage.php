<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {
            // Referral counts in the external API can exceed 32-bit INT.
            $table->unsignedBigInteger('total_inviter_number')
                ->default(0)
                ->change();

            // A referral code belongs to a competition, not globally to the app.
            $table->dropUnique('yasuser_refercode_unique');
            $table->unique(
                ['game_id', 'refercode'],
                'yasuser_game_id_refercode_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('yasuser', function (Blueprint $table) {
            $table->dropUnique('yasuser_game_id_refercode_unique');
            $table->unique('refercode', 'yasuser_refercode_unique');
            $table->unsignedInteger('total_inviter_number')
                ->default(0)
                ->change();
        });
    }
};
