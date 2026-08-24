<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('yasuser', function (Blueprint $table) {
            $table->id();
            $table->string('refercode')->unique();
            $table->string('compitetor_name')->nullable();
            $table->string('total_inviter_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yasuser');
    }
};
