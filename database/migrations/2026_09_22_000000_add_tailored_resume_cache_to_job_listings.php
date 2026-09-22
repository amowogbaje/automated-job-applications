<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            // Cached so "View tailored resume" is instant after the first
            // click — only re-tailors when the resume itself changed since
            // (tracked via tailored_for_resume_id) or someone forces it.
            $table->string('tailored_resume_path')->nullable()->after('description');
            $table->json('tailored_resume_snapshot')->nullable()->after('tailored_resume_path');
            $table->foreignId('tailored_for_resume_id')->nullable()->after('tailored_resume_snapshot')
                ->constrained('resumes')->nullOnDelete();
            $table->timestamp('tailored_resume_generated_at')->nullable()->after('tailored_for_resume_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tailored_for_resume_id');
            $table->dropColumn(['tailored_resume_path', 'tailored_resume_snapshot', 'tailored_resume_generated_at']);
        });
    }
};
