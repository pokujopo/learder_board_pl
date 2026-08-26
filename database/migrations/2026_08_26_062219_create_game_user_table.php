<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_user', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('game_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('refercode')->nullable();

    $table->boolean('refercode_verified')
        ->default(false);

    $table->timestamp('verified_at')->nullable();

    $table->timestamps();

    // User mmoja hawezi kujisajili mara mbili
    // kwenye game moja
    $table->unique([
        'user_id',
        'game_id',
    ]);

    // Refercode moja haiwezi kutumiwa
    // na users wawili ndani ya game moja
    $table->unique([
        'game_id',
        'refercode',
    ]);

    $table->index([
        'game_id',
        'refercode_verified',
    ]);
});

    }

    public function down(): void
    {
        Schema::dropIfExists('game_user');
    }
};