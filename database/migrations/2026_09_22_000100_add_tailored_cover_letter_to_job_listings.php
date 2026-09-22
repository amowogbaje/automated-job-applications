<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->text('tailored_cover_letter')->nullable()->after('tailored_resume_generated_at');
            $table->timestamp('tailored_cover_letter_generated_at')->nullable()->after('tailored_cover_letter');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['tailored_cover_letter', 'tailored_cover_letter_generated_at']);
        });
    }
};
