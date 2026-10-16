<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Component 1: Service Intelligence
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('skills_required')->nullable();
            $table->json('target_businesses')->nullable();
            $table->json('opportunity_signals')->nullable();
            $table->json('search_keywords')->nullable();
            $table->json('geographic_preferences')->nullable();
            // employment | contract | project | consulting | any
            $table->string('preferred_engagement')->default('any');
            $table->unsignedInteger('minimum_budget')->nullable();
            $table->string('budget_currency', 3)->default('USD');
            $table->boolean('is_employment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Component 7: Portfolio & CV
        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('url')->nullable();
            $table->text('summary')->nullable();
            $table->json('technologies')->nullable();
            $table->json('industries')->nullable();
            $table->text('proof_points')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('cv_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('service_portfolio_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('relevance')->default(3); // 1-5
            $table->timestamps();
            $table->unique(['service_id', 'portfolio_project_id'], 'svc_project_unique');
        });

        Schema::create('service_cv_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cv_version_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'cv_version_id'], 'svc_cv_unique');
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel')->default('email'); // email | whatsapp | form | any
            $table->text('body');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('service_cv_version');
        Schema::dropIfExists('service_portfolio_project');
        Schema::dropIfExists('cv_versions');
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('services');
    }
};
