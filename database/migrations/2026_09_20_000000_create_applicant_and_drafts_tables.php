<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table holding your parsed resume/experience. Kept separate
        // from users table so this works whether or not you add auth later.
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->longText('resume_raw_text')->nullable();
            $table->json('skills')->nullable();
            $table->text('summary')->nullable();
            $table->json('projects')->nullable(); // [{name, description, tech}, ...]
            $table->timestamps();
        });

        Schema::create('application_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->text('cover_letter')->nullable();
            $table->text('tailored_summary')->nullable(); // suggested resume-emphasis notes
            $table->enum('status', ['draft', 'ready', 'sent', 'discarded'])->default('draft');
            $table->timestamps();

            $table->unique('job_listing_id'); // one draft per job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_drafts');
        Schema::dropIfExists('applicant_profiles');
    }
};
