<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Resources\Api\V1\TaskListResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\TaskList;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskListTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskListService $taskLists,
    ) {}

    /**
     * Same shape as GET /inbox (D4) — the read model is identical whether
     * the list is the Inbox or any other list.
     *
     * Attaches `accepted_members_count` (Plan 1, Step 7) so `is_shared`/
     * `member_count` are available here too — this is the endpoint a client
     * actually sits on while viewing a list's contents, so it is the one
     * read path (beyond the index and `show()`) that most needs the flag. It
     * is still deliberately *not* attached on `store()`/`update()`'s return
     * paths, `GET /inbox` (the Inbox can never be shared — F9 — so the value
     * would always be false/1 there anyway), the nested `list` inside
     * `TaskResource`, or the nested `lists` inside `FolderResource` — those
     * remain a documented, not-yet-addressed gap rather than an oversight.
     */
    public function index(Request $request, TaskList $list): JsonResponse
    {
        $this->authorize('view', $list);

        $list = $this->taskLists->withMemberCount($list);

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
