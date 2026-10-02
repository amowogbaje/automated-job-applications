<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            // 'job' = regular board listing, 'social' = a post found on X / Mastodon / Hacker News.
            // Social posts live on their own page (/social) so they never mix into the main feed.
            $table->string('kind', 16)->default('job')->after('source')->index();

            // Translation is done ONCE (at fetch / by the 5-minute job) and stored here;
            // the "Show translation" button only swaps stored text, it never calls an API.
            $table->string('language', 8)->nullable()->after('description');
            $table->string('translation_status', 16)->default('none')->after('language'); // none|pending|done|failed
            $table->string('title_translated')->nullable()->after('translation_status');
            $table->text('description_translated')->nullable()->after('title_translated');
            $table->string('translation_provider', 32)->nullable()->after('description_translated');
            $table->unsignedTinyInteger('translation_attempts')->default(0)->after('translation_provider');
            $table->timestamp('translated_at')->nullable()->after('translation_attempts');

            // Social posts: what the AI pulled out of the post (all validated against the raw text).
            $table->string('social_platform', 24)->nullable();
            $table->string('social_author')->nullable();
            $table->text('apply_instructions')->nullable();
            $table->string('apply_subject')->nullable();
            $table->boolean('needs_manual_input')->default(false); // post asks for more than CV/cover letter
            $table->json('ai_extracted')->nullable();
            $table->timestamp('ai_extracted_at')->nullable();

            $table->index(['kind', 'posted_at']);
            $table->index(['translation_status', 'posted_at']);
        });

        Schema::table('career_profiles', function (Blueprint $table) {
            $table->boolean('social_jobs_enabled')->default(true)->after('open_to_work');
            // Separate, explicit opt-in: emailing strangers from a social post is riskier than a job board.
            $table->boolean('social_auto_email')->default(false)->after('social_jobs_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex(['kind', 'posted_at']);
            $table->dropIndex(['translation_status', 'posted_at']);
            $table->dropIndex(['kind']);
            $table->dropColumn([
                'kind', 'language', 'translation_status', 'title_translated', 'description_translated',
                'translation_provider', 'translation_attempts', 'translated_at',
                'social_platform', 'social_author', 'apply_instructions', 'apply_subject',
                'needs_manual_input', 'ai_extracted', 'ai_extracted_at',
            ]);
        });
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['social_jobs_enabled', 'social_auto_email']);
        });
    }
};
