<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // whose mailbox it leaves from
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outreach_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->boolean('attach_docs')->default(false);
            $table->timestamp('send_at')->index();
            $table->string('status', 12)->default('pending');   // pending | sending | sent | failed | cancelled | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'send_at']);
        });

        Schema::create('whatsapp_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->text('message');
            $table->timestamp('remind_at');
            $table->string('status', 12)->default('pending');   // pending | due | sent | dismissed
            $table->unsignedTinyInteger('nags')->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('source', 12)->default('manual');    // manual | sequence
            $table->timestamps();
            $table->index(['status', 'remind_at']);
        });

        Schema::table('agent_notifications', function (Blueprint $table) {
            $table->string('url', 500)->nullable()->after('body');   // where tapping it goes
        });
    }

    public function down(): void
    {
        Schema::table('agent_notifications', fn (Blueprint $t) => $t->dropColumn('url'));
        Schema::dropIfExists('whatsapp_reminders');
        Schema::dropIfExists('scheduled_emails');
    }
};
