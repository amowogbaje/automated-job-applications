<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every AI / search call, so rate limits and daily caps can be tracked.
        Schema::create('provider_calls', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);            // ai | search
            $table->string('provider', 40);
            $table->string('model', 100)->nullable();
            // ok|rate_limited|auth|server|network|rejected|invalid
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->text('error')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();
            $table->index(['kind', 'created_at']);
            $table->index(['provider', 'created_at']);
        });

        // Who created the record: me, or the research agent (so agent output is always identifiable).
        foreach (['evidence', 'contacts', 'opportunity_scores'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('created_by', 10)->default('manual');
            });
        }
    }

    public function down(): void
    {
        foreach (['evidence', 'contacts', 'opportunity_scores'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
        Schema::dropIfExists('provider_calls');
    }
};
