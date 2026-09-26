<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('affiliate_link_id')
                ->constrained('affiliate_links')
                ->cascadeOnDelete();

            $table->foreignId('referred_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique([
                'affiliate_link_id',
                'referred_user_id',
            ]);

            $table->index([
                'affiliate_link_id',
                'verified_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};