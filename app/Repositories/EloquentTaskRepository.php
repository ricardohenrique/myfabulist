<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\TaskReorderMismatchException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStar;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    /**
     * @return Collection<int, Task>
     */
    public function activeForList(TaskList $taskList, User $viewer): Collection
    {
        return Task::query()
            ->where('task_list_id', $taskList->id)
            ->where('is_completed', false)
            ->withStarredFor($viewer->id)
            ->with(['comments.author', 'subtasks'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    public function completedForList(TaskList $taskList, User $viewer): Collection
    {
        return Task::query()
            ->where('task_list_id', $taskList->id)
            ->where('is_completed', true)
            ->withStarredFor($viewer->id)
            ->with(['comments.author', 'subtasks'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Plan 1, Step 4 (code-review follow-up): scoped to the viewer's
     * accepted membership on the task's list, not merely "a `task_stars`
     * row exists" — starring never granted read access on its own, but
     * before this fix nothing else was gating this specific read path
     * either, since `TaskPolicy::update()` became a *membership* check in
     * this same step rather than the ownership check the old comment here
     * assumed still guarded every star-write entry point. Without this, a
     * removed collaborator (Step 5's revoke/leave) would permanently retain
     * read access to everything they had starred while still a member.
     *
     * @return Collection<int, Task>
     */
    public function starredForUser(User $user): Collection
    {
        $tasks = Task::query()
            ->whereHas('stars', fn (Builder $query) => $query->where('user_id', $user->id))
            // The only cross-list task query in the codebase (D2/Plan 4):
            // every other task read is reached *through* a TaskList that is
            // already resolved via a trashed-blind repository lookup, so a
            // deleted list's tasks are naturally invisible there. This one
            // queries tasks directly, so it needs its own guard — whereHas
            // applies TaskList's soft-delete global scope inside the
            // subquery, dropping any starred task whose list is trashed,
            // and the nested `members` condition is this method's access
            // predicate: only an accepted membership on the list makes a
            // starred task visible here.
            ->whereHas('taskList.members', fn (Builder $query) => $query->where('user_id', $user->id)->where('status', 'accepted'))
            ->withStarredFor($user->id)
            ->with(['comments.author', 'subtasks'])
            ->orderByDesc('created_at')
            ->get();

        return $this->attachTaskListPlacement($tasks, $user->id);
    }

    /**
     * See `starredForUser()` above for the access predicate this mirrors.
     */
    public function starredCountForUser(User $user): int
    {
        return Task::query()
            ->whereHas('stars', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('taskList.members', fn (Builder $query) => $query->where('user_id', $user->id)->where('status', 'accepted'))
            ->count();
    }

    /**
     * Scoped to the user's accepted membership on the task's list (Plan 1,
     * Step 4), not `tasks.user_id` — a task created by one member is
     * reachable by every other accepted member of its list.
     */
    public function findForUser(int $taskId, User $user): ?Task
    {
        return Task::query()
            ->whereHas('taskList.members', fn (Builder $query) => $query->where('user_id', $user->id)->where('status', 'accepted'))
            ->find($taskId);
    }

    /**
     * See `findForUser()` above for the membership scoping this method
     * mirrors for trashed tasks.
     */
    public function findDeletedForUser(int $taskId, User $user): ?Task
    {
        return Task::onlyTrashed()
            ->whereHas('taskList.members', fn (Builder $query) => $query->where('user_id', $user->id)->where('status', 'accepted'))
            ->find($taskId);
    }

    public function findForRouteBinding(int $taskId, ?User $viewer): ?Task
    {
        $query = Task::query();

        if ($viewer !== null) {
            $query->withStarredFor($viewer->id);
        }

        return $query->find($taskId);
    }

    /**
     * `$viewer` exists solely to resolve the embedded `taskList`'s
     * placement (`folder_id`/`position`) for the response — the plain
     * `Task::taskList(): BelongsTo` relation never chains
     * `TaskList::scopeJoinMemberPlacement()`, so a bare `$task->load('taskList')`
     * would silently emit `"list": {"folder_id": null, "position": null}`
     * for a task genuinely filed in a folder, the same class of regression
     * Step 2's review caught for `GET /api/v1/inbox`. `is_starred` on
     * `$task` itself needs no such fix here — it already arrived correctly
     * aliased via route binding before this method ever runs.
     */
    public function loadDetails(Task $task, User $viewer): Task
    {
        $task->setRelation('taskList', $this->taskListWithPlacementFor($task->task_list_id, $viewer->id));

        return $task->load(['comments.author', 'subtasks']);
    }

    /**
     * Resolves a single task's parent list with this viewer's placement
     * aliased on, the same `joinMemberPlacement(..., requireAccess: false)`
     * shape `EloquentTaskListRepository::findForRouteBinding()` uses — a
     * left join, not an inner one, so a list this viewer has no accepted
     * membership in still resolves (with a null placement) rather than
     * silently vanishing. Preserves the prior `$task->load('taskList')`
     * behaviour of resolving to `null` when the list is itself
     * soft-deleted, since `TaskList::query()` still carries its own
     * SoftDeletes global scope.
     */
    private function taskListWithPlacementFor(int $taskListId, int $viewerId): ?TaskList
    {
        return TaskList::query()
            ->joinMemberPlacement($viewerId, requireAccess: false)
            ->find($taskListId);
    }

    /**
     * Batched sibling of `taskListWithPlacementFor()` for a collection of
     * tasks that may span several lists (`starredForUser()`) — one extra
     * query for every distinct list, not one per task, so this can never
     * trip `Model::preventLazyLoading()` or add an N+1 (R5).
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    private function attachTaskListPlacement(Collection $tasks, int $viewerId): Collection
    {
        $taskListIds = $tasks->pluck('task_list_id')->unique()->values();

        if ($taskListIds->isEmpty()) {
            return $tasks;
        }

        $taskListsById = TaskList::query()
            ->joinMemberPlacement($viewerId, requireAccess: false)
            ->whereIn($this->qualifyTaskListsColumn('id'), $taskListIds)
            ->with('folder')
            ->get()
            ->keyBy('id');

        return $tasks->each(function (Task $task) use ($taskListsById): void {
            $task->setRelation('taskList', $taskListsById->get($task->task_list_id));
        });
    }

    /**
     * `joinMemberPlacement()` joins `task_list_members` onto `task_lists`,
     * so an unqualified `whereIn('id', ...)` here is ambiguous between the
     * two tables — qualify it against `task_lists` explicitly.
     */
    private function qualifyTaskListsColumn(string $column): string
    {
        return (new TaskList)->qualifyColumn($column);
    }

    public function create(User $user, TaskList $taskList, string $title, int $position): Task
    {
        $task = new Task;

        $task->forceFill([
            'user_id' => $user->id,
            'task_list_id' => $taskList->id,
            'title' => $title,
            'position' => $position,
        ])->save();

        // A freshly created task has no stars yet — attach that explicitly
        // (Plan 1, Step 3) rather than leaving `is_starred` unset, so a
        // create response is byte-identical to the old is_starred=false
        // column default (N6).
        return $task->withStarred(false);
    }

    /**
     * `title`/`note`/`due_date` are shared state, written to `tasks`
     * directly. `$isStarred` is per-viewer state routed to the
     * `task_stars` pivot via `star()`/`unstar()` instead of a task column
     * (Plan 1, Step 3) — transactional because this is now a multi-row
     * write (a `tasks` update plus a `task_stars` write), mirroring
     * `EloquentTaskListRepository::update()`'s Step 2 fix for the same
     * missing-transaction class of bug.
     */
    public function update(Task $task, User $user, string $title, ?string $note, ?Carbon $dueDate, bool $isStarred): Task
    {
        return DB::transaction(function () use ($task, $user, $title, $note, $dueDate, $isStarred): Task {
            $task->forceFill([
                'title' => $title,
                'note' => $note,
                'due_date' => $dueDate,
            ])->save();

            return $isStarred ? $this->star($task, $user) : $this->unstar($task, $user);
        });
    }

    public function rename(Task $task, string $title): Task
    {
        $task->forceFill(['title' => $title])->save();

        return $task;
    }

    public function markCompleted(Task $task): Task
    {
        if (! $task->is_completed) {
            $task->forceFill([
                'is_completed' => true,
                'completed_at' => now(),
            ])->save();
        }

        return $task;
    }

    public function markActive(Task $task): Task
    {
        if ($task->is_completed) {
            $task->forceFill([
                'is_completed' => false,
                'completed_at' => null,
            ])->save();
        }

        return $task;
    }

    /**
     * Idempotent — genuinely so, not just against sequential calls:
     * `insertOrIgnore()` is one atomic statement that relies on the unique
     * `(task_id, user_id)` constraint to silently no-op on a conflict,
     * rather than a check-then-insert (`exists()` then `save()`), which two
     * concurrent stars on the same task (a double-submit, two tabs, a
     * retry) can both pass the check for and then race to insert — the
     * loser throws a unique-constraint `QueryException`, which is worse
     * than it sounds here because `star()` runs inside `update()`'s
     * `DB::transaction`: the losing racer's rollback would also discard the
     * `title`/`note`/`due_date` changes that were legitimately part of the
     * same request. Bypasses mass assignment the same way `forceFill()`
     * did — `TaskStar` deliberately keeps `created_at` out of
     * `#[Fillable]` (matching `TaskListMember`'s own timestamp fields), and
     * `insertOrIgnore()` never goes through `fill()` at all.
     */
    public function star(Task $task, User $user): Task
    {
        TaskStar::query()->insertOrIgnore([[
            'task_id' => $task->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]]);

        return $task->withStarred(true);
    }

    /**
     * Idempotent: deleting zero rows (the user never starred this task, or
     * already unstarred it) is not an error.
     */
    public function unstar(Task $task, User $user): Task
    {
        TaskStar::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->delete();

        return $task->withStarred(false);
    }

    public function moveToList(Task $task, TaskList $taskList, ?int $position): Task
    {
        $task->forceFill([
            'task_list_id' => $taskList->id,
            'position' => $position ?? $this->nextPosition($taskList),
        ])->save();

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Un-delete a soft-deleted task (D3). This calls the Eloquent
     * `restore()` method on the model — unambiguous here, inside the
     * repository — never confuse it with `markActive()` above, which
     * un-completes a completed task.
     */
    public function undelete(Task $task): Task
    {
        $task->restore();

        return $task;
    }

    public function nextPosition(TaskList $taskList): int
    {
        $maxPosition = Task::query()->where('task_list_id', $taskList->id)->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    /**
     * Validates the submitted id set against the list's current tasks and
     * rewrites positions atomically. Throws (and writes nothing) when the
     * set is missing an id, contains an extra id, or contains a foreign id
     * (D13/D12) — the transaction guarantees the original order survives a
     * mismatch untouched.
     */
    public function applyOrder(TaskList $taskList, array $taskIds): void
    {
        DB::transaction(function () use ($taskList, $taskIds) {
            $currentIds = $this->idsForList($taskList)->all();

            $submitted = array_values($taskIds);

            if ($this->idSetsDiffer($currentIds, $submitted)) {
                throw TaskReorderMismatchException::for($taskList);
            }

            foreach ($submitted as $position => $taskId) {
                Task::query()
                    ->where('task_list_id', $taskList->id)
                    ->where('id', $taskId)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * @param  array<int, int>  $currentIds
     * @param  array<int, int>  $submittedIds
     */
    private function idSetsDiffer(array $currentIds, array $submittedIds): bool
    {
        sort($currentIds);
        sort($submittedIds);

        return $currentIds !== $submittedIds;
    }

    /**
     * @return SupportCollection<int, int>
     */
    public function idsForList(TaskList $taskList): SupportCollection
    {
        return Task::query()
            ->where('task_list_id', $taskList->id)
            ->where('is_completed', false)
            ->pluck('id');
    }
}
