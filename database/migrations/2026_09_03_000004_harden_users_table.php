<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('users', function(Blueprint $t){$t->index('role');}); } public function down(): void { Schema::table('users', fn(Blueprint $t)=>$t->dropIndex(['role'])); }};
