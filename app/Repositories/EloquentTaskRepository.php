<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\TaskReorderMismatchException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    /**
     * @return Collection<int, Task>
     */
    public function activeForList(TaskList $taskList): Collection
    {
        return Task::query()
            ->where('task_list_id', $taskList->id)
            ->where('is_completed', false)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    public function completedForList(TaskList $taskList): Collection
    {
        return Task::query()
            ->where('task_list_id', $taskList->id)
            ->where('is_completed', true)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    public function starredForUser(User $user): Collection
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->where('is_starred', true)
            ->with('taskList.folder')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForUser(int $taskId, User $user): ?Task
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->find($taskId);
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

        return $task;
    }

    public function update(Task $task, string $title, ?string $note, ?Carbon $dueDate, bool $isStarred): Task
    {
        $task->forceFill([
            'title' => $title,
            'note' => $note,
            'due_date' => $dueDate,
            'is_starred' => $isStarred,
        ])->save();

        return $task;
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

    public function setStarred(Task $task, bool $isStarred): Task
    {
        $task->forceFill(['is_starred' => $isStarred])->save();

        return $task;
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
            ->pluck('id');
    }
}
