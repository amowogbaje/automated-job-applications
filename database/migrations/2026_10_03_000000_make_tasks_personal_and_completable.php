<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Tasks used to be shared by every account. They now belong to one user.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable()->after('priority');
            $table->softDeletes(); // makes "Undo delete" possible
            $table->index(['user_id', 'priority']);
        });

        // Existing rows predate accounts being separated: give them to the earliest account (the owner).
        $owner = DB::table('users')->orderBy('id')->value('id');
        if ($owner) {
            DB::table('projects')->whereNull('user_id')->update(['user_id' => $owner]);
            DB::table('tasks')->whereNull('user_id')->update(['user_id' => $owner]);
        }

        // Priority is now one global order per user (it used to be per project, so numbers collided
        // across projects). Renumber 1..n in the order the old "All tasks" view showed them.
        foreach (DB::table('tasks')->whereNotNull('user_id')->distinct()->pluck('user_id') as $uid) {
            $n = 0;
            foreach (DB::table('tasks')->where('user_id', $uid)->orderBy('priority')->orderBy('id')->pluck('id') as $id) {
                DB::table('tasks')->where('id', $id)->update(['priority' => ++$n]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'priority']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'completed_at', 'deleted_at']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
