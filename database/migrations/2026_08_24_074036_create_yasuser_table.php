<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yasuser', function (Blueprint $table) {
            $table->id();

            $table->string('refercode')->unique();

            $table->string('compitetor_name')->nullable();

            $table->unsignedInteger('total_inviter_number')
                ->default(0);

            $table->timestamp('last_synced_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yasuser');
    }
};