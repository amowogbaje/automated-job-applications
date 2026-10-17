<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->longText('original_body')->nullable()->after('body');          // what the AI wrote, so edits can be reset
            $table->json('claims')->nullable()->after('evidence_ids');             // [{quote, evidence_id}] verified against the body
            $table->json('warnings')->nullable()->after('claims');
            $table->foreignId('portfolio_project_id')->nullable()->after('signature_id')->constrained('portfolio_projects')->nullOnDelete();
            $table->foreignId('cv_version_id')->nullable()->after('portfolio_project_id')->constrained('cv_versions')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('copied_at');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cv_version_id');
            $table->dropConstrainedForeignId('portfolio_project_id');
            $table->dropColumn(['original_body', 'claims', 'warnings', 'edited_at']);
        });
    }
};
