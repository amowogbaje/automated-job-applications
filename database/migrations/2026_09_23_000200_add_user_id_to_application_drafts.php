<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropUnique(['job_listing_id']); // was one draft per job, globally

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
            $table->unique('job_listing_id');
        });
    }
};
