<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 3: the contract half of
 * the star migration. `task_stars` (previous migration) has carried a
 * verified, one-row-per-starred-task backfill since it was created; this
 * migration drops the column and index it superseded so `task_stars`
 * becomes the sole source of truth for whether a task is starred, and by
 * whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_starred']);
            $table->dropColumn('is_starred');
        });
    }

    /**
     * Schema-reversible, not data-reversible: this restores the dropped
     * column (defaulted, so the rollback itself never fails) but cannot
     * repopulate it from nothing. A rollback only reconstructs real star
     * data if `task_stars` still exists and is backfilled back into this
     * column by a separate, explicit step — i.e. this `down()` is only
     * meaningful run directly after this migration's own `up()`, before
     * the previous migration's table is ever dropped.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_starred')->default(false)->after('completed_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'is_starred']);
        });
    }
};
