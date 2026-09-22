<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Leads are always owned — unlike resumes, there's no
            // seed-then-claim step here, they're created directly by
            // whoever runs a discovery search or adds one manually.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('company_name');
            $table->string('domain')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('industry')->nullable();
            $table->string('headcount')->nullable();

            // Which stacks made this company worth reaching out to —
            // e.g. ["nestjs","fastapi","golang"]. Informational only,
            // not verified against anything Hunter actually detected
            // unless the discovery query specifically matched on it.
            $table->json('tech_stack')->nullable();

            // Contact details, filled in only when "reveal contact" is
            // run (a separate, credit-costing step) or entered manually —
            // never populated automatically just from a Discover match.
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_position')->nullable();

            $table->enum('source', ['hunter_discover', 'manual'])->default('manual');
            $table->enum('status', ['new', 'contacted', 'replied', 'won', 'lost'])->default('new');
            $table->text('notes')->nullable();

            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            // One row per company per user — re-running a discovery
            // search shouldn't duplicate a lead you already have.
            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
