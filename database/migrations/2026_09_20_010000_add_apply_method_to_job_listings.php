<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->enum('apply_method', ['email', 'web'])->default('web')->after('url');
            $table->string('apply_email')->nullable()->after('apply_method');
            $table->timestamp('applied_at')->nullable()->after('is_applied');
            $table->timestamp('notified_at')->nullable()->after('applied_at'); // when included in a digest email
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['apply_method', 'apply_email', 'applied_at', 'notified_at']);
        });
    }
};
