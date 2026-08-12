<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\Data\TaskDetailsData;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function handle(Task $task, User $user, TaskDetailsData $details, int $targetListId): Task
    {
        return DB::transaction(function () use ($task, $user, $details, $targetListId): Task {
            $task = $this->tasks->update($task, $user, $details);

            if ($task->task_list_id !== $targetListId) {
                $task = $this->tasks->move($task, $user, $targetListId, null);
            }

            return $task;
        });
    }
}
