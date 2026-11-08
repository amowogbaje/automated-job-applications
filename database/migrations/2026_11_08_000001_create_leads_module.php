<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('source')->nullable()->after('type');       // 'uploaded' for leads you brought yourself
            $table->string('lead_batch')->nullable()->after('source');
            $table->index('source');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_customer')->default(false);
            $table->date('customer_since')->nullable();
        });

        Schema::create('lead_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete(); // the email being written to
            $table->string('mode', 10)->default('email');       // email | manual
            $table->string('state', 20)->default('active');     // active | paused | done | stopped
            $table->unsignedTinyInteger('step')->default(0);    // steps completed in this mode
            $table->unsignedTinyInteger('total_steps')->default(3);
            $table->unsignedTinyInteger('reminders')->default(0);
            $table->unsignedTinyInteger('failures')->default(0);
            $table->boolean('auto_send')->default(true);        // still needs the global switch to be on
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();
            $table->index(['state', 'next_action_at']);
        });

        Schema::create('agent_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 30);          // phone_due | email_due | info | closed | failure
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('read_at');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 14, 2);                  // negative for a refund
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 12)->default('pending');   // pending | paid | cancelled
            $table->date('occurred_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('shared_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('attach_to_emails')->default(false); // included in the automatic follow-ups
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_documents');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('agent_notifications');
        Schema::dropIfExists('lead_sequences');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['is_customer', 'customer_since']));
        Schema::table('opportunities', function (Blueprint $t) {
            $t->dropIndex(['source']);
            $t->dropColumn(['source', 'lead_batch']);
        });
    }
};
