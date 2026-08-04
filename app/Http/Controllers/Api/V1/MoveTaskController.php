<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MoveTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;

class MoveTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function __invoke(MoveTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->tasks->move($task, $request->user(), $request->targetListId(), $request->position());

        return TaskResource::make($task)->response();
    }
}
