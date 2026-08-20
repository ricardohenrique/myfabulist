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
     * `$viewer` is whose star state to alias onto each row as `is_starred`
     * (Plan 1, Step 3) — not an access restriction, every caller has
     * already resolved `$taskList` through an access-scoped path.
     *
     * @return Collection<int, Task>
     */
    public function activeForList(TaskList $taskList, User $viewer): Collection;

    /**
     * Completed tasks in the list, most recently completed first. See
     * `activeForList()` for what `$viewer` is used for.
     *
     * @return Collection<int, Task>
     */
    public function completedForList(TaskList $taskList, User $viewer): Collection;

    /**
     * Every task the user has starred, across all lists, with
     * `taskList.folder` eager-loaded. Joins/filters via `task_stars`
     * (Plan 1, Step 3) rather than `tasks.user_id` — a task created by one
     * user can be starred by any list member. `is_starred` is aliased for
     * consistency with every other read path, even though every row here
     * is definitionally starred by this user.
     *
     * Scoped to the viewer's *accepted membership* on the task's list
     * (Plan 1, Step 4 — code-review follow-up), not merely "a `task_stars`
     * row exists": a star is never itself an access grant, and once
     * `TaskPolicy::update()` became a membership check (this same step)
     * nothing else was left gating this read path. Without this, a removed
     * collaborator (Step 5's revoke/leave) would permanently retain read
     * access to everything they had starred while still a member.
     *
     * @return Collection<int, Task>
     */
    public function starredForUser(User $user): Collection;

    /**
     * The count backing the sidebar's Starred badge — same access predicate
     * as starredForUser() (accepted membership; trashed-list tasks
     * excluded) without loading the rows themselves.
     */
    public function starredCountForUser(User $user): int;

    /**
     * Find a task by id, scoped to the given user's accepted membership on
     * its list (Plan 1, Step 4) — not `tasks.user_id`. Returns null when
     * the task does not exist or the user has no accepted membership on its
     * list.
     */
    public function findForUser(int $taskId, User $user): ?Task;

    /**
     * Find a non-deleted task with its non-deleted list for queued internal
     * workflows that already carry their own recipient authorization.
     */
    public function findWithTaskList(int $taskId): ?Task;

    /**
     * Find a *soft-deleted* task by id, scoped to the given user's accepted
     * membership on its list (D3/Plan 4; Plan 1, Step 4 widened this from
     * `tasks.user_id` to membership, consistent with `TaskPolicy`'s fully
     * collaborative task-level delegation). Returns null when the task does
     * not exist, the user has no accepted membership on its list, or the
     * task is not currently trashed.
     */
    public function findDeletedForUser(int $taskId, User $user): ?Task;

    /**
     * Resolve a task by id for implicit route-model-binding (see
     * `App\Providers\AppServiceProvider::configureRouteBindings()`),
     * regardless of whether $viewer can access it — `TaskPolicy` needs the
     * task to exist so it can deny access with 403; scoping this lookup to
     * the viewer would turn a cross-user request into an indistinguishable
     * 404 instead. `is_starred` is aliased for `$viewer` when one is given,
     * and left absent when there is no authenticated user. Every other
     * read path must stay scoped to the caller's own access — this is not
     * a general-purpose lookup and must never be called from application
     * code.
     */
    public function findForRouteBinding(int $taskId, ?User $viewer): ?Task;

    /**
     * Load a task's relations for detail display, plus re-resolve its
     * embedded `taskList` with `$viewer`'s placement (`folder_id`/
     * `position`) aliased on — `Task::taskList(): BelongsTo` never chains
     * `TaskList::scopeJoinMemberPlacement()` on its own, so this method is
     * where that gap is closed for the one response shape that embeds a
     * full `TaskListResource` inside a task.
     */
    public function loadDetails(Task $task, User $viewer): Task;

    public function create(User $user, TaskList $taskList, string $title, int $position): Task;

    public function update(Task $task, User $user, string $title, ?string $note, ?Carbon $dueDate, bool $isStarred): Task;

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

    /**
     * Idempotent: starring an already-starred task a second time is a
     * no-op, not a duplicate `task_stars` row.
     */
    public function star(Task $task, User $user): Task;

    /**
     * Idempotent: unstarring a task the user never starred is a no-op.
     */
    public function unstar(Task $task, User $user): Task;

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

    /**
     * Existing, non-deleted task ids scoped to the supplied list ids.
     *
     * @param  array<int, int>  $taskIds
     * @param  array<int, int>  $listIds
     * @return SupportCollection<int, int>
     */
    public function existingIdsInLists(array $taskIds, array $listIds): SupportCollection;
}
