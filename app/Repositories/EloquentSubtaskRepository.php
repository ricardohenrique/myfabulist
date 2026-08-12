<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Subtask;
use App\Models\Task;
use App\Repositories\Contracts\SubtaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentSubtaskRepository implements SubtaskRepositoryInterface
{
    /**
     * @return Collection<int, Subtask>
     */
    public function forTask(Task $task): Collection
    {
        return Subtask::query()
            ->where('task_id', $task->id)
            ->oldest()
            ->orderBy('id')
            ->get();
    }

    public function create(Task $task, string $title): Subtask
    {
        $subtask = new Subtask;

        $subtask->forceFill([
            'task_id' => $task->id,
            'title' => $title,
            'is_completed' => false,
        ])->save();

        return $subtask;
    }

    public function rename(Subtask $subtask, string $title): Subtask
    {
        $subtask->forceFill(['title' => $title])->save();

        return $subtask;
    }

    public function markCompleted(Subtask $subtask): Subtask
    {
        if (! $subtask->is_completed) {
            $subtask->forceFill(['is_completed' => true])->save();
        }

        return $subtask;
    }

    public function markActive(Subtask $subtask): Subtask
    {
        if ($subtask->is_completed) {
            $subtask->forceFill(['is_completed' => false])->save();
        }

        return $subtask;
    }

    public function delete(Subtask $subtask): void
    {
        $subtask->delete();
    }
}
