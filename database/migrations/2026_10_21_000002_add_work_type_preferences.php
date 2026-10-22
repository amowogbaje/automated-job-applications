<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->json('work_types')->nullable();      // ["remote","hybrid"]; empty = no preference
            $table->json('work_locations')->nullable();  // places for onsite/hybrid work
        });
        Schema::table('services', function (Blueprint $table) {
            $table->json('work_modes')->nullable();      // how this service can be delivered; empty = any
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('work_mode', 10)->nullable(); // remote|hybrid|onsite when the source says so
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', fn (Blueprint $t) => $t->dropColumn('work_mode'));
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('work_modes'));
        Schema::table('career_profiles', fn (Blueprint $t) => $t->dropColumn(['work_types', 'work_locations']));
    }
};
