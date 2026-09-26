<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('affiliate_referral_id')
                ->constrained('affiliate_referrals')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('points');

            $table->string('reason', 100);

            $table->timestamps();

            $table->unique('affiliate_referral_id');

            $table->index([
                'user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_rewards');
    }
};