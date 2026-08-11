<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return TaskResource::make($this->tasks->details($task))->response();
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->tasks->update($task, $request->toData());

        return TaskResource::make($this->tasks->details($task))->response();
    }

    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task);

        return response()->noContent();
    }
}
