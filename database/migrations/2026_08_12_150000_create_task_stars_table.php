<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 3: creates the per-user
 * star pivot and backfills it from `tasks.is_starred`. Unlike Steps 1-2
 * (an expand migration followed by a separate contract migration), this
 * single migration does create + backfill; dropping `tasks.is_starred`
 * itself is the *next* migration
 * (`2026_08_12_160000_drop_is_starred_from_tasks_table.php`), for the same
 * reason Step 2 kept its two halves apart — the backfill must be
 * independently verifiable before the column it backfilled from disappears.
 *
 * `created_at` has no accompanying `updated_at` — a star has no "updated"
 * concept, only created/deleted (see `App\Models\TaskStar`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_stars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        $this->backfillStars();
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stars');
    }

    /**
     * One star row per pre-existing starred task, owned by the task's
     * current `user_id` — under today's schema a task has exactly one
     * owner, so that owner is the only person who could have starred it.
     * A single `INSERT ... SELECT` (via `insertUsing`), not a chunked
     * read-then-insert loop — same atomicity/portability reasoning as
     * `create_task_list_members_table`'s backfill (Step 1's review).
     *
     * Deliberately does not filter out soft-deleted tasks: a trashed task
     * is recoverable (D3), and its star state — like every other
     * attribute — must come back with it on undelete, exactly as
     * `EloquentTaskRepository::undelete()` already relies on for
     * `title`/`note`/`position`. `starredForUser()`'s own `whereHas('taskList')`
     * guard (not this migration) is what keeps a trashed-list task's star
     * out of the live Starred view.
     */
    private function backfillStars(): void
    {
        DB::table('task_stars')->insertUsing(
            ['task_id', 'user_id', 'created_at'],
            DB::table('tasks')
                ->select(['id', 'user_id', 'created_at'])
                ->where('is_starred', true),
        );
    }
};
