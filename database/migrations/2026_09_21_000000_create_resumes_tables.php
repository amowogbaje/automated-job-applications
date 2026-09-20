<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The header/summary row. You can import more than once (e.g. re-parsing
        // after updating your PDF) — only one row is ever `is_active` at a time,
        // and that's the one used to compile PDFs and feed the AI. Old imports
        // stay in the table so you can compare or roll back.
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            // Nullable on purpose: a resume can be seeded before any account
            // exists (see ResumeSeeder / resume:claim command) and stays
            // unowned until explicitly attached to a user.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['pdf_upload', 'website', 'merged', 'manual'])->default('manual');
            $table->boolean('is_active')->default(true);

            // Header / contact block
            $table->string('full_name');
            $table->string('headline')->nullable();       // e.g. "Full Stack / Backend Developer — Laravel, Node.js, Go"
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('website_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();

            $table->text('summary')->nullable();           // 2-4 sentence professional summary

            // Full extracted text kept for audit / re-parsing / fallback context to the AI.
            $table->longText('raw_pdf_text')->nullable();
            $table->longText('raw_website_text')->nullable();

            $table->timestamps();
        });

        Schema::create('resume_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('job_title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();           // null + is_current = "Present"
            $table->boolean('is_current')->default(false);
            $table->json('bullets')->nullable();            // array of achievement strings
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('resume_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('institution');
            $table->string('degree')->nullable();
            $table->string('field')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('resume_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            // Free-text category so new buckets (e.g. "Applied AI") don't need a migration.
            $table->string('category')->default('other'); // backend, frontend, database, apis_integrations, applied_ai, testing_devops, other
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['resume_id', 'category', 'name']);
        });

        Schema::create('resume_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('tech_stack')->nullable();          // array of strings
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('resume_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_certifications');
        Schema::dropIfExists('resume_projects');
        Schema::dropIfExists('resume_skills');
        Schema::dropIfExists('resume_education');
        Schema::dropIfExists('resume_experiences');
        Schema::dropIfExists('resumes');
    }
};
