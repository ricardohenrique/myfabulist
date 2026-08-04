<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TaskList;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Data\ListedTasks;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /**
     * The M4/M6 read model for a list: active tasks before completed tasks,
     * completed tasks most-recently-completed first, plus a completed count.
     */
    public function tasksFor(TaskList $taskList): ListedTasks
    {
        $completed = $this->tasks->completedForList($taskList);

        return new ListedTasks(
            active: $this->tasks->activeForList($taskList),
            completed: $completed,
            completedCount: $completed->count(),
        );
    }
}
