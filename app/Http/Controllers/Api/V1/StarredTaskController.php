<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StarredTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    /**
     * Cross-list query over the current user's starred tasks (S2) —
     * not a list or folder. Eager-loads taskList.folder so the client can
     * render each task's parent list/folder without N+1.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tasks = $this->tasks->starredFor($request->user());

        return TaskResource::collection($tasks)->response();
    }
}
