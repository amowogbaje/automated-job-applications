<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source');          // arbeitnow, remoteok, weworkremotely, adzuna, himalayas
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('url_hash', 64); // sha1 of url+title+company for dedup
            $table->unsignedTinyInteger('match_score')->default(0); // keyword relevance
            $table->json('matched_keywords')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->boolean('is_applied')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique('url_hash');
            $table->index(['posted_at']);
            $table->index(['source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
