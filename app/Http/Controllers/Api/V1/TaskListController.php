<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskListRequest;
use App\Http\Requests\Api\V1\UpdateTaskListRequest;
use App\Http\Resources\Api\V1\TaskListResource;
use App\Models\TaskList;
use App\Services\TaskListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskListController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskLists,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaskList::class);

        $lists = $this->taskLists->allFor($request->user());

        return TaskListResource::collection($lists)->response();
    }

    public function store(StoreTaskListRequest $request): JsonResponse
    {
        $list = $this->taskLists->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('folder_id'),
        );

        return TaskListResource::make($list)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TaskList $list): JsonResponse
    {
        $this->authorize('view', $list);

        return TaskListResource::make($list->load('folder'))->response();
    }

    public function update(UpdateTaskListRequest $request, TaskList $list): JsonResponse
    {
        $list = $this->taskLists->update(
            $list,
            $request->user(),
            $request->validated('name'),
            $request->validated('folder_id'),
        );

        return TaskListResource::make($list)->response();
    }

    public function destroy(Request $request, TaskList $list): Response
    {
        $this->authorize('delete', $list);

        $this->taskLists->delete($list, $request->user());

        return response()->noContent();
    }
}
