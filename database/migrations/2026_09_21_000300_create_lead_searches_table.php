<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('query')->nullable();
            $table->json('stacks')->nullable();
            $table->json('countries')->nullable();
            $table->json('regions')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedInteger('new_leads_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_searches');
    }
};
