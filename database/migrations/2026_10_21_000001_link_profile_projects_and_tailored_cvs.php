<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One project list: Profile projects are mirrored into the agent's portfolio_projects.
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->foreignId('resume_project_id')->nullable()->unique()->after('id')
                ->constrained('resume_projects')->nullOnDelete();
        });

        // Resumes generated per opportunity live beside the uploaded CVs but are kept apart.
        Schema::table('cv_versions', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('id')->constrained('opportunities')->nullOnDelete();
            $table->unsignedBigInteger('resume_id')->nullable()->after('opportunity_id');
            $table->json('snapshot')->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('cv_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropColumn(['resume_id', 'snapshot']);
        });
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resume_project_id');
        });
    }
};
