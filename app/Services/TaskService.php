<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidTaskTitleException;
use App\Exceptions\TaskCannotBeUndeletedException;
use App\Exceptions\TaskListNotFoundException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Data\ListedTasks;
use App\Services\Data\TaskDetailsData;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskListRepositoryInterface $taskLists,
    ) {}

    /**
     * The M4/M6 read model for a list: active tasks before completed tasks,
     * completed tasks most-recently-completed first, plus a completed
     * count. `$viewer` is whose star state is aliased onto each task as
     * `is_starred` (Plan 1, Step 3).
     */
    public function tasksFor(TaskList $taskList, User $viewer): ListedTasks
    {
        $completed = $this->tasks->completedForList($taskList, $viewer);

        return new ListedTasks(
            active: $this->tasks->activeForList($taskList, $viewer),
            completed: $completed,
            completedCount: $completed->count(),
        );
    }

    /**
     * Quick-capture a task (M3). Title is the only required field; a
     * blank/whitespace-only title is rejected here regardless of which
     * delivery mechanism called in — this is the invariant, not just a
     * transport-level validation rule.
     */
    public function create(User $user, TaskList $taskList, string $title): Task
    {
        $title = $this->requireNonBlankTitle($title);

        return $this->tasks->create($user, $taskList, $title, $this->tasks->nextPosition($taskList));
    }

    /**
     * Replace a task's title/note/due date/star state in one call (D4).
     * `$user` is the acting user, whose own `task_stars` row is what
     * `$data->isStarred` toggles (Plan 1, Step 3) — this never touches
     * another user's star.
     */
    public function update(Task $task, User $user, TaskDetailsData $data): Task
    {
        $title = $this->requireNonBlankTitle($data->title);

        return $this->tasks->update($task, $user, $title, $data->note, $data->dueDate, $data->isStarred);
    }

    /**
     * `$viewer` resolves the embedded `taskList`'s placement for the
     * caller — see `TaskRepositoryInterface::loadDetails()`.
     */
    public function details(Task $task, User $viewer): Task
    {
        return $this->tasks->loadDetails($task, $viewer);
    }

    public function rename(Task $task, string $title): Task
    {
        return $this->tasks->rename($task, $this->requireNonBlankTitle($title));
    }

    /**
     * Idempotent: completing an already-completed task is a no-op (M5).
     */
    public function complete(Task $task): Task
    {
        return $this->tasks->markCompleted($task);
    }

    /**
     * Idempotent: restoring an already-active task is a no-op (M5).
     *
     * Not to be confused with `undelete()` below, which un-deletes a
     * soft-deleted task — this method only ever un-completes one (D3).
     * A trashed task's `deleted_at` is untouched by this call.
     */
    public function restore(Task $task): Task
    {
        return $this->tasks->markActive($task);
    }

    /**
     * Star or unstar a task on behalf of `$user` — always their own star,
     * never another list member's (Plan 1, Step 3).
     */
    public function setStarred(Task $task, User $user, bool $isStarred): Task
    {
        return $isStarred ? $this->tasks->star($task, $user) : $this->tasks->unstar($task, $user);
    }

    /**
     * Move a task to another of the user's lists (S6). The target list
     * reference arrives as an id and is resolved here, scoped strictly to
     * ownership via `findOwnedBy()` (D3) — a missing or foreign list id
     * throws, preserving D7's "a task can only move between lists
     * belonging to the same user" invariant regardless of caller.
     *
     * Deliberately *not* widened to membership (Plan 1, Step 4/Q8): a task
     * can never move into a list the acting user merely has an accepted
     * membership on, only one they own — `findOwnedBy()`, not
     * `findAccessibleFor()`, is what keeps that boundary intact even after
     * Step 5 ships real invitations.
     */
    public function move(Task $task, User $user, int $targetListId, ?int $position): Task
    {
        $targetList = $this->taskLists->findOwnedBy($targetListId, $user)
            ?? throw TaskListNotFoundException::forId($targetListId);

        return $this->tasks->moveToList($task, $targetList, $position);
    }

    public function delete(Task $task): void
    {
        $this->tasks->delete($task);
    }

    /**
     * Un-deletes a soft-deleted task. Not to be confused with `restore()`
     * above, which un-completes a completed task (D3) — this method never
     * touches `is_completed`/`completed_at`.
     *
     * The acting user is required (D6) to resolve the task's parent list
     * through the already access-scoped, trashed-blind
     * `TaskListRepositoryInterface::findAccessibleFor()` — a task whose list
     * is itself deleted cannot be un-deleted, or "Undo" would report success
     * while hiding the task somewhere the user can no longer reach it. Any
     * accepted member can undelete a task in a list they can access (Plan
     * 1, Step 4), not just the list's owner — consistent with
     * `TaskPolicy`'s fully-collaborative task-level delegation.
     */
    public function undelete(Task $task, User $user): Task
    {
        $this->taskLists->findAccessibleFor($task->task_list_id, $user)
            ?? throw TaskCannotBeUndeletedException::becauseItsListIsDeleted($task);

        return $this->tasks->undelete($task);
    }

    /**
     * Bulk reorder (S4). The repository validates the submitted id set
     * against the list's current tasks and rewrites positions atomically,
     * throwing TaskReorderMismatchException before writing anything on any
     * mismatch (missing, extra, or foreign ids).
     *
     * @param  array<int, int>  $taskIds
     */
    public function reorder(TaskList $taskList, array $taskIds): void
    {
        $this->tasks->applyOrder($taskList, $taskIds);
    }

    /**
     * Every starred task belonging to the user, across all lists (S2).
     *
     * @return Collection<int, Task>
     */
    public function starredFor(User $user): Collection
    {
        return $this->tasks->starredForUser($user);
    }

    private function requireNonBlankTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw InvalidTaskTitleException::becauseBlank();
        }

        return $title;
    }
}
