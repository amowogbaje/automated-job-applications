<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            // Null means "use this account's own login email" — only set
            // this if you want digests/applications routed somewhere else.
            $table->string('notification_email')->nullable()->after('min_required_matches');

            // Defaults to false, same safety reasoning the old global
            // AUTO_SEND_APPLICATIONS had: review a batch of drafts first,
            // then opt in per account at /profile.
            $table->boolean('auto_send_enabled')->default(false)->after('notification_email');

            // Whether this account gets the hourly digest at all.
            $table->boolean('digest_enabled')->default(true)->after('auto_send_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['notification_email', 'auto_send_enabled', 'digest_enabled']);
        });
    }
};
