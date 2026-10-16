<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Component 5: Contact Intelligence
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            // email|whatsapp|phone|contact_form|careers_portal|professional_profile|other
            $table->string('type', 30);
            $table->string('value', 500);         // address / number / URL (kept short: it is part of a unique index)
            $table->string('label')->nullable();   // e.g. "Sales enquiries"
            $table->string('person_name')->nullable();
            $table->string('person_role')->nullable();
            $table->string('source_url', 2048);   // required: where it was publicly found
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_public_business_channel')->default(false);
            $table->boolean('is_preferred')->default(false);
            $table->unsignedTinyInteger('channel_rank')->nullable();
            $table->text('restrictions')->nullable(); // contact instructions, "no unsolicited", legal notes
            $table->string('status')->default('active'); // active|bounced|invalid|opted_out
            $table->timestamps();
            $table->index(['company_id', 'type']);
            $table->unique(['company_id', 'type', 'value'], 'contact_unique');
        });

        // Component 6: Outreach Studio
        Schema::create('outreach_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signature_id')->nullable()->constrained()->nullOnDelete();
            // email|whatsapp|contact_form|application|call_script
            $table->string('channel', 30);
            // direct|problem_solving|experience_led (or channel-specific labels)
            $table->string('approach', 40);
            $table->string('subject')->nullable();
            $table->json('alt_subjects')->nullable();
            $table->longText('body');
            $table->json('evidence_ids')->nullable(); // evidence rows each claim relies on
            $table->unsignedSmallInteger('version')->default(1);
            // draft|selected|edited|sent|discarded
            $table->string('status')->default('draft');
            $table->boolean('is_selected')->default(false);
            $table->string('ai_model')->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['opportunity_id', 'channel', 'status']);
        });

        // Manual send / reply log
        Schema::create('outreach_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            // sent|reply|call|meeting|bounce|no_response|note
            $table->string('event_type', 30);
            $table->string('channel', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['opportunity_id', 'occurred_at']);
        });

        // Component 8: Tracking
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_auto')->default(false);
            $table->timestamps();
            $table->index(['due_at', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('outreach_events');
        Schema::dropIfExists('outreach_drafts');
        Schema::dropIfExists('contacts');
    }
};
