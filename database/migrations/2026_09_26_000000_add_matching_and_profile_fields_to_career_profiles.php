<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            // Hide "remote" roles whose text says US-only / must reside in EU, etc.
            $table->boolean('hide_region_restricted')->default(false)->after('min_required_matches');
            // Shown on the profile page ("Open to work"); also lets you pause the feed's digest framing.
            $table->boolean('open_to_work')->default(true)->after('hide_region_restricted');
        });
    }

    public function down(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['hide_region_restricted', 'open_to_work']);
        });
    }
};
