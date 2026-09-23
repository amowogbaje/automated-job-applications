<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Same four knobs that used to live in .env as JOB_KEYWORDS,
            // JOB_REQUIRED_SKILLS, JOB_EXCLUDED_KEYWORDS, JOB_MIN_REQUIRED_MATCHES —
            // now per account instead of shared across the whole install.
            $table->json('keywords')->nullable();
            $table->json('required_skills')->nullable();
            $table->json('excluded_keywords')->nullable();
            $table->unsignedTinyInteger('min_required_matches')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_profiles');
    }
};
