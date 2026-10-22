<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('web_presence', 12)->nullable(); // website | social_only | unknown (what the source shows, not a quality judgement)
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', fn (Blueprint $t) => $t->dropColumn('web_presence'));
    }
};
