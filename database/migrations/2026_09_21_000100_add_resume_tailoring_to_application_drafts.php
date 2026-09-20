<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_drafts', function (Blueprint $table) {
            // What the AI chose to lead with for this specific job: a tailored
            // one-line headline, which of your real skills/projects to surface
            // first, and which to leave out. Never adds anything not already
            // in the `resumes`/`resume_*` tables — see ResumeTailor.
            $table->json('resume_snapshot')->nullable()->after('tailored_summary');

            // Path to the PDF compiled from resumes + resume_snapshot for this
            // job (see ResumeCompiler). This is what actually gets attached to
            // an auto-sent application, instead of a static file on disk.
            $table->string('resume_pdf_path')->nullable()->after('resume_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropColumn(['resume_snapshot', 'resume_pdf_path']);
        });
    }
};
