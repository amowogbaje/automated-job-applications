<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Component 3: agent execution log (tool-using research agent)
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_run_id')->nullable()->constrained()->nullOnDelete();
            // research|match|contact|outreach
            $table->string('kind', 30);
            $table->text('objective')->nullable();
            $table->string('status')->default('pending'); // pending|running|completed|failed|limit_reached
            $table->unsignedSmallInteger('step_limit')->default(12);
            $table->unsignedSmallInteger('steps_used')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_estimate', 10, 4)->default(0);
            $table->string('ai_model')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });

        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_number');
            // SearchWeb|InspectWebsite|FindCompanyServices|FindHiringSignals|FindBusinessContact|
            // SearchCompanyNews|CheckExistingLead|SaveResearchEvidence|Reasoning
            $table->string('tool', 60);
            $table->json('input')->nullable();
            $table->longText('output')->nullable();
            $table->boolean('succeeded')->default(true);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->unique(['agent_run_id', 'step_number']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject');
            $table->string('action', 60);
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('action');
        });

        // Editable config: score weights, caps, default country list, etc.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('agent_steps');
        Schema::dropIfExists('agent_runs');
    }
};
