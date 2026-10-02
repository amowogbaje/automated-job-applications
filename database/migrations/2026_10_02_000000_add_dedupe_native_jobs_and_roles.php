<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // seeker | employer. Different navigation, dashboards and permissions.
            $table->string('role', 16)->default('seeker')->after('email')->index();
            // Employer-only company profile (shown on the job pages they post).
            $table->string('company_name')->nullable()->after('role');
            $table->string('company_website')->nullable()->after('company_name');
            $table->string('company_size', 16)->nullable()->after('company_website');
            $table->text('company_description')->nullable()->after('company_size');
        });

        Schema::table('job_listings', function (Blueprint $table) {
            // De-duplication: the same job found on several boards/platforms is stored once.
            $table->char('fingerprint', 40)->nullable()->index();      // sha1(company|title), normalised
            $table->string('apply_key')->nullable()->index();          // ats:vendor:board:id | url:canonical apply link
            $table->char('simhash', 16)->nullable()->index();          // near-duplicate TEXT (cross-posted posts)
            $table->unsignedBigInteger('canonical_id')->nullable()->index(); // set => hidden duplicate of that listing
            $table->json('also_posted')->nullable();                   // [{source, url, posted_at}] other places it appeared

            // Jobs posted directly on this app by employers (source = 'native').
            $table->foreignId('employer_id')->nullable()->after('kind')->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('published')->after('employer_id')->index(); // draft|published|closed
            $table->string('employment_type', 24)->nullable()->after('is_remote');             // full_time|part_time|contract|internship
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->json('skills')->nullable();                        // declared, canonical skills for matching
            $table->timestamp('closes_at')->nullable();
        });

        // Applications made inside the app (to native jobs).
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained()->nullOnDelete();
            $table->text('cover_letter')->nullable();
            $table->string('resume_pdf_path')->nullable();
            $table->string('status', 16)->default('new')->index();    // new|reviewing|shortlisted|rejected|hired
            $table->text('employer_note')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->unique(['job_listing_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');

        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->dropColumn([
                'fingerprint', 'apply_key', 'simhash', 'canonical_id', 'also_posted',
                'employer_id', 'status', 'employment_type', 'salary_min', 'salary_max',
                'salary_currency', 'skills', 'closes_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'company_name', 'company_website', 'company_size', 'company_description']);
        });
    }
};
