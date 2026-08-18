<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TaskListRepositoryInterface
{
    /**
     * All of the user's lists, with folder eager-loaded and tasks counted.
     * Each list carries `tasks_count` (all tasks), `active_tasks_count`
     * (incomplete tasks only — used for sidebar badges), and
     * `accepted_members_count` (Plan 1, Step 7 — `TaskListResource`'s
     * `member_count`/`is_shared`).
     *
     * @return Collection<int, TaskList>
     */
    public function allForUser(User $user): Collection;

    /**
     * Find a list by id, scoped to any accepted membership the user holds —
     * owner or shared member alike (Plan 1, Step 4). Returns null when the
     * list does not exist or the user has no accepted membership on it.
     */
    public function findAccessibleFor(int $taskListId, User $user): ?TaskList;

    /**
     * Find a list by id, scoped strictly to ownership — deliberately
     * *not* widened to membership (Plan 1, Step 4/Q8). The sole caller is
     * `TaskService::move()`'s target-list resolution: a task can never be
     * moved into a list the acting user merely has membership on, only one
     * they own, so this method must keep excluding shared-but-not-owned
     * lists forever. Do not call this from anywhere else — every other
     * "can this user reach this list" read is `findAccessibleFor()`.
     */
    public function findOwnedBy(int $taskListId, User $user): ?TaskList;

    /**
     * Find a *soft-deleted* list by id, requiring ownership on top of an
     * accepted membership (D3/Plan 4; Plan 1, Step 4 kept this
     * ownership-scoped on purpose, deliberately *not* widened to "any
     * accepted member" like `findAccessibleFor()`). Only the owner can
     * `delete()` a shared list in the first place
     * (`TaskListPolicy::delete()`), so only the owner can undelete it back —
     * a non-owner member never had a delete path here to undo. Returns null
     * when the list does not exist, is not owned by the given user, or is
     * not currently trashed.
     */
    public function findDeletedForUser(int $taskListId, User $user): ?TaskList;

    public function findDefaultFor(User $user): ?TaskList;

    /**
     * Resolve a list by id for implicit route-model-binding, regardless of
     * whether $viewer can access it — the binding must still succeed so
     * `TaskListPolicy` can deny access with 403 rather than the lookup
     * itself turning a cross-user request into an indistinguishable 404.
     * Every other caller must use `findAccessibleFor()`/`allForUser()` instead —
     * this method is not scoped to the viewer's own lists on purpose and
     * must never be called from application code.
     */
    public function findForRouteBinding(int $taskListId, ?User $viewer): ?TaskList;

    /**
     * Load `accepted_members_count` onto a single, already-resolved list —
     * the single-list counterpart to `allForUser()`'s `withCount()` (Plan 1,
     * Step 7). Used by `TaskListController::show()` so `is_shared`/
     * `member_count` are also available on the single-list read path, the
     * other place (besides the index) a client decides whether to render a
     * share-management affordance. Not applied to `findForRouteBinding()`
     * itself on purpose — that resolver backs every route with a `{list}`
     * segment, including task/subtask/comment sub-resources that never read
     * `member_count`, so attaching the count there would add an unused
     * subquery to requests that have nothing to do with sharing.
     */
    public function withAcceptedMemberCount(TaskList $taskList): TaskList;

    /**
     * Create the user's default Inbox list. The sole creation point for the
     * default list (D5).
     */
    public function createDefaultFor(User $user): TaskList;

    public function create(User $user, string $name, ?Folder $folder, int $position): TaskList;

    /**
     * Rename and/or reposition a list. `name` is shared state written to
     * `task_lists`; `$folder`/`$position` are the *acting user's own*
     * placement, written to their membership row (Plan 1, Step 2) — every
     * member of a shared list files it wherever they like without moving
     * it for anyone else.
     */
    public function update(TaskList $taskList, User $user, string $name, ?Folder $folder, int $position): TaskList;

    /**
     * Move only the acting user's placement of a list. Unlike update(), this
     * deliberately does not write the shared list name, so a drag operation
     * cannot overwrite a concurrent rename with stale client data.
     */
    public function move(TaskList $taskList, User $user, ?Folder $folder, int $position): TaskList;

    public function delete(TaskList $taskList): void;

    /**
     * Un-delete a soft-deleted list (D3/Plan 4), bringing back every task it
     * contained exactly as it was — soft-deleting a list never touches its
     * tasks (D2), so nothing else needs restoring.
     */
    public function undelete(TaskList $taskList): TaskList;

    public function nextPosition(User $user, ?int $folderId): int;

    /**
     * Rewrite the position of every list in $taskListIds to match the
     * array's order, atomically.
     *
     * @param  array<int, int>  $taskListIds
     */
    public function applyOrder(User $user, ?int $folderId, array $taskListIds): void;
}
