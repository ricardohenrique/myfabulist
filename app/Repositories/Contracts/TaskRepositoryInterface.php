<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

interface TaskRepositoryInterface
{
    /**
     * Active (incomplete) tasks in the list, ordered by position then id.
     *
     * @return Collection<int, Task>
     */
    public function activeForList(TaskList $taskList): Collection;

    /**
     * Completed tasks in the list, most recently completed first.
     *
     * @return Collection<int, Task>
     */
    public function completedForList(TaskList $taskList): Collection;

    /**
     * Every starred task belonging to the user, across all lists, with
     * `taskList.folder` eager-loaded.
     *
     * @return Collection<int, Task>
     */
    public function starredForUser(User $user): Collection;

    /**
     * The count backing the sidebar's Starred badge — same scoping as
     * starredForUser() (trashed-list tasks excluded) without loading the
     * rows themselves.
     */
    public function starredCountForUser(User $user): int;

    /**
     * Find a task by id, scoped to the given user. Returns null when the
     * task does not exist or belongs to a different user.
     */
    public function findForUser(int $taskId, User $user): ?Task;

    /**
     * Find a *soft-deleted* task by id, scoped to the given user (D3/Plan 4).
     * Returns null when the task does not exist, belongs to a different
     * user, or is not currently trashed.
     */
    public function findDeletedForUser(int $taskId, User $user): ?Task;

    public function create(User $user, TaskList $taskList, string $title, int $position): Task;

    public function update(Task $task, string $title, ?string $note, ?Carbon $dueDate, bool $isStarred): Task;

    public function rename(Task $task, string $title): Task;

    /**
     * The sole writer of `is_completed = true` / `completed_at = now()`
     * (D13). Idempotent: leaves `completed_at` untouched when already
     * completed.
     */
    public function markCompleted(Task $task): Task;

    /**
     * The sole writer of `is_completed = false` / `completed_at = null`
     * (D13). Idempotent.
     */
    public function markActive(Task $task): Task;

    public function setStarred(Task $task, bool $isStarred): Task;

    public function moveToList(Task $task, TaskList $taskList, ?int $position): Task;

    public function delete(Task $task): void;

    /**
     * Un-delete a soft-deleted task (D3/Plan 4). Not to be confused with
     * `markActive()`, which un-completes a completed task.
     */
    public function undelete(Task $task): Task;

    public function nextPosition(TaskList $taskList): int;

    /**
     * Rewrite the position of every task in $taskIds to match the array's
     * order, atomically. Callers must validate the id set first.
     *
     * @param  array<int, int>  $taskIds
     */
    public function applyOrder(TaskList $taskList, array $taskIds): void;

    /**
     * Every active (non-completed) task id currently in the list, for
     * reorder-set validation — completed tasks are excluded because the
     * sortable UI never submits their ids (D1).
     *
     * @return SupportCollection<int, int>
     */
    public function idsForList(TaskList $taskList): SupportCollection;
}
