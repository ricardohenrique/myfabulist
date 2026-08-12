<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\TaskListNotFoundException;
use App\Exceptions\TaskListReorderMismatchException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTaskListRepository implements TaskListRepositoryInterface
{
    public function __construct(
        private readonly TaskListMemberRepositoryInterface $taskListMembers,
    ) {}

    /**
     * @return Collection<int, TaskList>
     */
    public function allForUser(User $user): Collection
    {
        // joinMemberPlacement() aliases task_list_members' folder_id/
        // position onto the TaskList row, so every downstream consumer
        // (TaskListResource, WorkspacePresenter, NavigationService's
        // groupBy('folder_id')) keeps reading them as plain attributes with
        // zero changes of their own (Plan 1, Step 2).
        //
        // Plan 1, Step 4: the join's own condition — an accepted
        // task_list_members row for $user — is now this method's *entire*
        // access predicate; the extra where(task_lists.user_id) filter Step
        // 2 deliberately left in place is removed here. This method feeds
        // NavigationService::treeFor() (the sidebar): a list this user
        // merely accepted an invitation to, not one they own, must still
        // appear in their own tree — that is the whole point of accepting.
        // Behaviour-preserving today (every list still has exactly one
        // member, its owner, until Step 5 ships real invitations).
        return TaskList::query()
            ->joinMemberPlacement($user->id)
            ->with('folder')
            ->withCount('tasks')
            ->withCount(['tasks as active_tasks_count' => fn (Builder $query) => $query->where('is_completed', false)])
            ->orderBy('task_list_members.position')
            ->orderBy('task_lists.id')
            ->get();
    }

    /**
     * Scoped to any accepted membership, not ownership (Plan 1, Step 4) —
     * see the interface docblock. `TaskService::move()` must never call
     * this; it uses `findOwnedBy()` instead.
     */
    public function findAccessibleFor(int $taskListId, User $user): ?TaskList
    {
        return TaskList::query()
            ->joinMemberPlacement($user->id)
            ->find($taskListId);
    }

    /**
     * Scoped strictly to ownership (Plan 1, Step 4/Q8) — see the interface
     * docblock for why this must stay narrower than `findAccessibleFor()`
     * forever. No `joinMemberPlacement()` here on purpose: the sole caller
     * (`TaskService::move()`) only ever reassigns `task_list_id` onto the
     * result, it never reads the target list's own placement.
     */
    public function findOwnedBy(int $taskListId, User $user): ?TaskList
    {
        return TaskList::query()
            ->where('user_id', $user->id)
            ->find($taskListId);
    }

    /**
     * Scoped to ownership *and* accepted membership — `joinMemberPlacement()`
     * (default `requireAccess: true`) inner-joins on an accepted
     * `task_list_members` row, and the explicit `where` on top of it further
     * requires that row's user to be the list's owner. See the interface
     * docblock for why this stays ownership-scoped rather than widening to
     * "any accepted member" like `findAccessibleFor()` (Plan 1, Step 4).
     */
    public function findDeletedForUser(int $taskListId, User $user): ?TaskList
    {
        return TaskList::onlyTrashed()
            ->joinMemberPlacement($user->id)
            ->where('task_lists.user_id', $user->id)
            ->find($taskListId);
    }

    public function findDefaultFor(User $user): ?TaskList
    {
        return TaskList::query()
            ->joinMemberPlacement($user->id)
            ->where('task_lists.user_id', $user->id)
            ->where('task_lists.is_default', true)
            ->first();
    }

    /**
     * Resolve a list by id for implicit route-model-binding (see
     * `App\Providers\AppServiceProvider::configureRouteBindings()`),
     * regardless of whether $viewer can access it — `TaskListPolicy` needs
     * the list to exist so it can deny access with 403; scoping this lookup
     * to the viewer would turn a cross-user request into an indistinguishable
     * 404 instead. Every other read path must stay scoped to the caller's
     * own access (`findAccessibleFor()`/`allForUser()`) — this is not a
     * general-purpose lookup and must never be called from application code.
     */
    public function findForRouteBinding(int $taskListId, ?User $viewer): ?TaskList
    {
        $query = TaskList::query();

        if ($viewer !== null) {
            $query->joinMemberPlacement($viewer->id, requireAccess: false);
        }

        return $query->find($taskListId);
    }

    public function createDefaultFor(User $user): TaskList
    {
        // task_list_members is the sole placement write, not a dual-write
        // (Plan 1, Step 2 retired the Step 1 dual-write once the placement
        // columns were dropped from task_lists). Transactional so a failed
        // membership insert can never leave a list committed with no owner
        // membership row (mirrors applyOrder()'s transaction below).
        return DB::transaction(function () use ($user): TaskList {
            $taskList = new TaskList;

            $taskList->forceFill([
                'user_id' => $user->id,
                'name' => 'Inbox',
                'is_default' => true,
            ])->save();

            $this->taskListMembers->createOwnerMembership($taskList, null, 0);

            // Attach the placement onto the returned instance so it matches
            // what a fresh allForUser() read would show, without an extra
            // query — TaskListResource/WorkspacePresenter read folder_id/
            // position directly off whatever TaskList they're handed,
            // including this one, which never goes through allForUser()'s
            // join. withPlacement() (not a raw setAttribute()) so this
            // never leaves the instance carrying a phantom dirty attribute
            // that a later, unrelated ->save() would try to persist.
            return $taskList->withPlacement(null, 0);
        });
    }

    public function create(User $user, string $name, ?Folder $folder, int $position): TaskList
    {
        // See the comment in createDefaultFor() above.
        return DB::transaction(function () use ($user, $name, $folder, $position): TaskList {
            $taskList = new TaskList;

            $taskList->forceFill([
                'user_id' => $user->id,
                'name' => $name,
                'is_default' => false,
            ])->save();

            $this->taskListMembers->createOwnerMembership($taskList, $folder?->id, $position);

            return $taskList->withPlacement($folder?->id, $position);
        });
    }

    public function update(TaskList $taskList, User $user, string $name, ?Folder $folder, int $position): TaskList
    {
        // "name" is the only shared state written to task_lists here;
        // folder/position are the acting user's own placement, written to
        // their membership row (Plan 1, Step 2) — every member of a shared
        // list can move it in their own sidebar without affecting anyone
        // else's. Transactional like every other multi-row write in this
        // class (create, createDefaultFor, applyOrder, ...), and the
        // membership is resolved *before* the rename is written — fail
        // fast, write nothing on the failure path, rather than committing a
        // rename and then throwing on a missing membership row.
        //
        // Plan 1, Step 4 (code-review follow-up): this repository does not
        // trust the caller alone to have already checked membership status
        // — `findMembership()` deliberately returns a row of *any* status
        // (it is also what `TaskListPolicy` reads pending/declined off of),
        // so this method must itself require `status === 'accepted'` before
        // treating the row as a valid membership to rename/reposition
        // through. Unreachable via HTTP today (`UpdateTaskListRequest`
        // already authorizes via the accepted-only `update` ability), but
        // this project's own convention — see `TaskService::move()`'s own
        // `findOwnedBy()` — is that the repository layer enforces its
        // invariants regardless of caller, not just the policy in front of it.
        return DB::transaction(function () use ($taskList, $user, $name, $folder, $position): TaskList {
            $membership = $this->taskListMembers->findMembership($taskList, $user);

            if ($membership === null || $membership->status !== 'accepted') {
                throw TaskListNotFoundException::forId($taskList->id);
            }

            $taskList->forceFill(['name' => $name])->save();

            $this->taskListMembers->updatePlacement($membership, $folder?->id, $position);

            return $taskList->withPlacement($folder?->id, $position);
        });
    }

    public function delete(TaskList $taskList): void
    {
        $taskList->delete();
    }

    /**
     * Un-delete a soft-deleted list (D3). Calls the Eloquent `restore()`
     * method on the model — unambiguous here, inside the repository.
     */
    public function undelete(TaskList $taskList): TaskList
    {
        $taskList->restore();

        return $taskList;
    }

    public function nextPosition(User $user, ?int $folderId): int
    {
        // task_lists no longer carries position at all (Plan 1, Step 2);
        // delegate entirely to the membership repository, which already
        // scopes to accepted rows and excludes soft-deleted lists.
        return $this->taskListMembers->nextPositionFor($user, $folderId);
    }

    public function applyOrder(User $user, ?int $folderId, array $taskListIds): void
    {
        DB::transaction(function () use ($user, $folderId, $taskListIds) {
            // Locks and rewrites task_list_members rows instead of
            // task_lists rows (Plan 1, Step 2) — scoped to this user's own
            // accepted membership, still excluding is_default lists (that
            // flag stays on task_lists, so it is checked through the
            // taskList relation).
            $currentIds = TaskListMember::query()
                ->where('user_id', $user->id)
                ->where('folder_id', $folderId)
                ->where('status', 'accepted')
                ->whereHas('taskList', fn (Builder $query) => $query->where('is_default', false))
                ->lockForUpdate()
                ->pluck('task_list_id')
                ->all();
            $submittedIds = array_values($taskListIds);
            $expectedIds = $currentIds;
            $candidateIds = $submittedIds;

            sort($expectedIds);
            sort($candidateIds);

            if ($expectedIds !== $candidateIds) {
                throw TaskListReorderMismatchException::forCurrentContainer();
            }

            foreach ($submittedIds as $position => $taskListId) {
                TaskListMember::query()
                    ->where('user_id', $user->id)
                    ->where('task_list_id', $taskListId)
                    ->update(['position' => $position]);
            }
        });
    }
}
