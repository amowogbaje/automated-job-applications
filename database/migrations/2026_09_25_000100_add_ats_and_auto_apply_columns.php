<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->string('ats')->nullable()->after('apply_email');          // greenhouse|lever|ashby|...
            $table->string('ats_board')->nullable()->after('ats');            // board slug / token
            $table->string('ats_job_id')->nullable()->after('ats_board');
            $table->text('apply_url')->nullable()->after('ats_job_id');       // direct apply form link
            $table->index(['ats', 'ats_board']);
        });

        // status was enum(draft|ready|sent|discarded); auto-apply adds in-flight
        // states (pending = submitted to a provider, awaiting its webhook;
        // needs_input = a screening question couldn't be answered truthfully).
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });

        Schema::table('application_drafts', function (Blueprint $table) {
            $table->string('apply_channel')->nullable()->after('status');     // email|greenhouse_api|lever_api|apply_service|manual
            $table->string('external_ref')->nullable()->after('apply_channel')->index(); // provider session/application id
            $table->timestamp('submitted_at')->nullable()->after('external_ref');
            $table->text('receipt_url')->nullable()->after('submitted_at');
            $table->json('pending_questions')->nullable()->after('receipt_url'); // needs_input: questions we couldn't answer
            $table->text('error')->nullable()->after('pending_questions');
        });

        Schema::table('career_profiles', function (Blueprint $table) {
            // off = never touch ATS apply; review = prepare drafts only; auto = submit within the daily cap
            $table->string('auto_apply_mode')->default('review')->after('digest_enabled');
            $table->unsignedSmallInteger('daily_apply_cap')->default(5)->after('auto_apply_mode');
            $table->json('ats_boards')->nullable()->after('daily_apply_cap');  // ["greenhouse:stripe", "lever:spotify"]
            // Answers to recurring screening questions, written once by the user:
            // {"work_authorization": "...", "notice_period": "...", "salary_expectation": "...", "relocate": "..."}
            $table->json('screening_answers')->nullable()->after('ats_boards');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex(['ats', 'ats_board']);
            $table->dropColumn(['ats', 'ats_board', 'ats_job_id', 'apply_url']);
        });
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropColumn(['apply_channel', 'external_ref', 'submitted_at', 'receipt_url', 'pending_questions', 'error']);
        });
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['auto_apply_mode', 'daily_apply_cap', 'ats_boards', 'screening_answers']);
        });
    }
};
