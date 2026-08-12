<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubtaskRequest;
use App\Http\Resources\Api\V1\SubtaskResource;
use App\Models\Task;
use App\Services\SubtaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskSubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
    ) {}

    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return SubtaskResource::collection($this->subtasks->forTask($task))->response();
    }

    public function store(StoreSubtaskRequest $request, Task $task): JsonResponse
    {
        $subtask = $this->subtasks->create($task, $request->validated('title'));

        return SubtaskResource::make($subtask)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
