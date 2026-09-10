<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();

            $table->string('refercode', 255);

            /*
             * Data received from the external referral API.
             */
            $table->string('customer_name')->nullable();

            /*
             * Keep this as string.
             * Phone/invitor numbers should not be stored as integers.
             */
            $table->string('invitor_number')->nullable();

            /*
             * Never store the plain token.
             * Only its SHA-256 hash is stored.
             */
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index([
                'game_id',
                'refercode',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_verifications');
    }
};