<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listing_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('is_applied')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();

            // Set the moment a digest email included this job for this
            // user — stops the same listing appearing in next run's digest,
            // per account, instead of globally for everyone.
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            // One state row per (job, user) pair — the whole point.
            $table->unique(['job_listing_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listing_user_states');
    }
};
