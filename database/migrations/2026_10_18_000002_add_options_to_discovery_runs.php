<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->json('options')->nullable()->after('queries');   // extra_queries, auto_research
            $table->json('result')->nullable()->after('error');      // counts, rejected reasons, created ids
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropColumn(['options', 'result']);
        });
    }
};
