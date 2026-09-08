<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The composite unique index is already created by the game_user table migration.
    }

    public function down(): void
    {
        // Intentionally left empty. The index belongs to the base game_user schema.
    }
};
