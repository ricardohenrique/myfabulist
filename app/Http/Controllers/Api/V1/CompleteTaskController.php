<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;

class CompleteTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    /**
     * Idempotent command: completing an already-completed task is a no-op.
     */
    public function __invoke(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return TaskResource::make($this->tasks->complete($task))->response();
    }
}
