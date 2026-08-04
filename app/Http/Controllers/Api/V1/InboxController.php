<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskListResource;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskListService,
        private readonly TaskService $taskService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $inbox = $this->taskListService->inboxFor($request->user());
        $tasks = $this->taskService->tasksFor($inbox);

        return response()->json([
            'data' => TaskListResource::withTasks($inbox, $tasks),
        ]);
    }
}
