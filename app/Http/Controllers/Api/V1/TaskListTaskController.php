<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Resources\Api\V1\TaskListResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\TaskList;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskListTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    /**
     * Same shape as GET /inbox (D4) — the read model is identical whether
     * the list is the Inbox or any other list.
     */
    public function index(Request $request, TaskList $list): JsonResponse
    {
        $this->authorize('view', $list);

        return response()->json([
            'data' => TaskListResource::withTasks($list, $this->tasks->tasksFor($list, $request->user())),
        ]);
    }

    public function store(StoreTaskRequest $request, TaskList $list): JsonResponse
    {
        $task = $this->tasks->create($request->user(), $list, $request->validated('title'));

        return TaskResource::make($task)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
