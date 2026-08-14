<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 2: the contract half of
 * expand -> migrate -> contract. `task_list_members` (Step 1) has carried a
 * verified, one-row-per-list backfill of every list's placement since it was
 * created; this migration drops the columns it superseded so
 * `task_list_members` becomes the sole source of truth for where a list
 * sits in a user's sidebar. `task_lists.user_id` is untouched — it remains
 * the authoritative owner pointer (Architecture Decision 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropIndex(['user_id', 'folder_id', 'position']);
            $table->dropColumn(['folder_id', 'position']);
        });
    }

    /**
     * Schema-reversible, not data-reversible: this restores the dropped
     * columns (nullable/defaulted, so the rollback itself never fails) but
     * cannot repopulate them from nothing. A rollback only reconstructs
     * real placement data if `task_list_members` still exists and is
     * backfilled back into these columns by a separate, explicit step —
     * i.e. this `down()` is only meaningful run directly after this
     * migration's own `up()`, before Step 1's table is ever dropped.
     */
    public function down(): void
    {
        Schema::table('task_lists', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('name');
        });

        Schema::table('task_lists', function (Blueprint $table) {
            $table->index(['user_id', 'folder_id', 'position']);
        });
    }
};
