<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_skills', function (Blueprint $table) {
            // "production", "personal-project level", ... — kept OUT of the
            // skill name so matching/dedup stays clean, but never lost.
            $table->string('qualifier')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('resume_skills', function (Blueprint $table) {
            $table->dropColumn('qualifier');
        });
    }
};
