<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Component 2: Discovery
        Schema::create('discovery_runs', function (Blueprint $table) {
            $table->id();
            // technical_need | hiring | external_assistance | funded_growth | portfolio_fit | manual
            $table->string('strategy');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->json('queries')->nullable();
            $table->string('status')->default('pending'); // pending|running|completed|failed
            $table->unsignedInteger('candidates_found')->default(0);
            $table->unsignedInteger('candidates_new')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->decimal('cost_estimate', 10, 4)->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['strategy', 'status']);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable()->unique(); // normalized, no scheme/www
            $table->string('website_url')->nullable();
            $table->string('industry')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('size_band')->nullable(); // 1-10, 11-50, 51-200, 200+
            $table->text('description')->nullable();
            $table->json('services_offered')->nullable();
            $table->timestamp('last_inspected_at')->nullable();
            $table->boolean('do_not_contact')->default(false);
            $table->string('do_not_contact_reason')->nullable();
            $table->timestamps();
            $table->index('country');
            $table->index('industry');
        });

        // Component 3/4: Opportunities
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('title');
            // job_posting | rfp | growth_signal | inferred_need | referral | manual
            $table->string('type')->default('inferred_need');
            $table->string('source_url')->nullable();
            $table->string('source_hash', 64)->nullable()->index(); // dedupe
            $table->text('summary')->nullable();
            $table->text('business_need')->nullable();     // what is evidenced
            $table->text('inferred_opportunity')->nullable(); // what AI infers (kept separate)
            $table->string('engagement_type')->nullable(); // employment|contract|project|consulting
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->string('budget_currency', 3)->nullable();
            $table->boolean('budget_is_verified')->default(false);
            $table->date('posted_at')->nullable();
            $table->date('expires_at')->nullable();

            // discovered|researching|qualified|rejected|drafted|approved|contacted|replied|won|lost|archived
            $table->string('status')->default('discovered');
            $table->string('priority', 1)->nullable(); // A | B | C
            $table->string('rejection_reason')->nullable();
            $table->unsignedTinyInteger('total_score')->nullable(); // 0-100, derived from components
            $table->string('confidence')->nullable(); // low|moderate|high
            $table->text('recommended_action')->nullable();
            $table->string('recommended_channel')->nullable();
            $table->text('channel_rationale')->nullable();

            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('total_score');
            $table->index('type');
        });

        // A company may match several services; one is primary
        Schema::create('opportunity_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('match_strength')->default(0); // 0-100
            $table->boolean('is_primary')->default(false);
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'service_id']);
        });

        // Component scores stored individually (weights live in settings)
        Schema::create('opportunity_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            // need_evidence|skills_match|active_signal|commercial_value|contact_channel|portfolio_strength
            $table->string('criterion', 40);
            $table->unsignedTinyInteger('score'); // 0-100 raw
            $table->unsignedTinyInteger('weight'); // weight % used at scoring time
            $table->text('reasoning')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'criterion']);
        });

        // Evidence trail
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 40); // website|job_listing|news|funding|directory|rfp|other
            $table->string('source_url', 2048);
            $table->string('source_title')->nullable();
            $table->timestamp('retrieved_at');
            $table->text('finding');            // what was found
            $table->text('establishes')->nullable(); // what this actually proves (fact)
            $table->text('inference')->nullable();   // what the AI inferred (not fact)
            $table->string('confidence')->default('moderate'); // low|moderate|high
            $table->text('excerpt')->nullable(); // short supporting snippet
            $table->boolean('is_stale')->default(false);
            $table->timestamps();
            $table->index(['opportunity_id', 'kind']);
        });

        Schema::create('opportunity_portfolio_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('rank')->default(1);
            $table->timestamps();
            $table->unique(['opportunity_id', 'portfolio_project_id'], 'opp_project_unique');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('recommended_cv_version_id')->nullable()
                ->constrained('cv_versions')->nullOnDelete();
            $table->text('cv_rationale')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_cv_version_id');
            $table->dropColumn('cv_rationale');
        });
        Schema::dropIfExists('opportunity_portfolio_project');
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('opportunity_scores');
        Schema::dropIfExists('opportunity_services');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('discovery_runs');
    }
};
