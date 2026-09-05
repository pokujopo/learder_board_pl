<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { 
    public function up(): void { 
        Schema::create('idempotency_keys', 
        function(Blueprint $t){
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('key',128);
            $t->string('endpoint',255);
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->json('response_body')->nullable();
            $t->timestamps();
            $t->unique(['user_id','key','endpoint']);}); }

    public function down(): void {
        Schema::dropIfExists('idempotency_keys');
    }};
