<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MoveTaskListRequest;
use App\Http\Resources\Api\V1\TaskListResource;
use App\Models\TaskList;
use App\Services\TaskListService;
use Illuminate\Http\JsonResponse;

class MoveTaskListController extends Controller
{
    public function __construct(private readonly TaskListService $taskLists) {}

    public function __invoke(MoveTaskListRequest $request, TaskList $list): JsonResponse
    {
        $list = $this->taskLists->move($list, $request->user(), $request->folderId());

        return TaskListResource::make($list)->response();
    }
}
