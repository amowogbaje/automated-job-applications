<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A match is now decided ONCE, at ingest, and stored per (user, job). The feed and the emails
        // then read straight from SQL instead of re-scoring hundreds of rows on every page view.
        Schema::table('job_listing_user_states', function (Blueprint $table) {
            $table->unsignedSmallInteger('score')->nullable()->after('notified_at');
            $table->string('tier', 10)->nullable()->after('score');
            $table->json('matched')->nullable()->after('tier');
            $table->json('title_hits')->nullable()->after('matched');
            $table->json('warnings')->nullable()->after('title_hits');
            $table->timestamp('matched_at')->nullable()->after('warnings');

            $table->index(['user_id', 'matched_at']);
            $table->index(['user_id', 'tier', 'score']);
        });

        Schema::table('career_profiles', function (Blueprint $table) {
            // Highest job id this profile has been matched against (the "watermark" for jobs:match).
            $table->unsignedBigInteger('last_matched_id')->default(0);
            // Opt-in email alerts. digest_enabled is reused as the on/off switch.
            $table->string('alert_frequency', 10)->default('daily');   // hourly | daily
            $table->string('alert_min_tier', 10)->default('good');     // strong | good | weak
            $table->timestamp('last_alert_at')->nullable();
        });

        // The hourly "new jobs" emails were the thing that got turned off. Alerts are opt-in again,
        // so nobody is switched back on by this upgrade.
        DB::table('career_profiles')->update(['digest_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['last_matched_id', 'alert_frequency', 'alert_min_tier', 'last_alert_at']);
        });

        Schema::table('job_listing_user_states', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'matched_at']);
            $table->dropIndex(['user_id', 'tier', 'score']);
            $table->dropColumn(['score', 'tier', 'matched', 'title_hits', 'warnings', 'matched_at']);
        });
    }
};
