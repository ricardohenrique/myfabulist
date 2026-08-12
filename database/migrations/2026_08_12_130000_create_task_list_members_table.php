<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 1: creates the membership
 * pivot and backfills it with one accepted owner row per pre-existing
 * `task_lists` row. This is a pure expand-phase migration — `task_lists`
 * itself is untouched, and `task_lists.folder_id`/`position` remain the
 * canonical read source for every existing code path until Step 2 cuts
 * placement over to this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['task_list_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'folder_id', 'position']);
        });

        $this->backfillOwnerMemberships();
    }

    public function down(): void
    {
        Schema::dropIfExists('task_list_members');
    }

    /**
     * One accepted owner-membership row per pre-existing list, copying its
     * current `folder_id`/`position` verbatim. A single `INSERT ... SELECT`
     * (via `insertUsing`) rather than a chunked read-then-insert round trip:
     * the whole backfill is one atomic, driver-portable statement, with no
     * PHP-side row marshalling and no risk of hitting SQLite's bound-
     * parameter cap on a large `task_lists` table. `invited_by_user_id` and
     * `invited_at` are `NULL` — an owner was never invited, unlike
     * `responded_at`, which is genuinely `task_lists.created_at`: the
     * owner's membership was "accepted" the moment the list existed. This
     * mirrors `EloquentTaskListMemberRepository::createOwnerMembership()`'s
     * write for real list creation exactly.
     */
    private function backfillOwnerMemberships(): void
    {
        DB::table('task_list_members')->insertUsing(
            ['task_list_id', 'user_id', 'status', 'folder_id', 'position', 'invited_by_user_id', 'invited_at', 'responded_at', 'created_at', 'updated_at'],
            DB::table('task_lists')->select([
                'id',
                'user_id',
                DB::raw("'accepted'"),
                'folder_id',
                'position',
                DB::raw('NULL'),
                DB::raw('NULL'),
                'created_at',
                'created_at',
                'updated_at',
            ]),
        );
    }
};
