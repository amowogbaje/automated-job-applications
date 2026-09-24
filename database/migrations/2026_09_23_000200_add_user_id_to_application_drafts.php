<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL won't drop application_drafts_job_listing_id_unique while
        // it's still backing job_listing_id's foreign key (error 1553) —
        // the FK has to come off first, in its own schema call, before the
        // index it depends on can go.
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropForeign(['job_listing_id']);
        });

        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropUnique(['job_listing_id']); // was one draft per job, globally
        });

        Schema::table('application_drafts', function (Blueprint $table) {
            // Re-add the FK now that nothing depends on the old index.
            $table->foreign('job_listing_id')->references('id')->on('job_listings')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->after('job_listing_id')
                ->constrained()->cascadeOnDelete();

            $table->unique(['job_listing_id', 'user_id']); // now one draft per job, per user
        });
    }

    public function down(): void
    {
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropUnique(['job_listing_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropForeign(['job_listing_id']);
        });

        Schema::table('application_drafts', function (Blueprint $table) {
            $table->foreign('job_listing_id')->references('id')->on('job_listings')->cascadeOnDelete();
            $table->unique('job_listing_id');
        });
    }
};
