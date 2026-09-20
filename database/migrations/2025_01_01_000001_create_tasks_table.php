<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('name');
            // Lower number = higher priority. #1 is the top-most task.
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
