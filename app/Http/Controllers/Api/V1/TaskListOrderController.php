<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTaskListOrderRequest;
use App\Services\TaskListService;
use Illuminate\Http\Response;

class TaskListOrderController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskLists,
    ) {}

    public function __invoke(UpdateTaskListOrderRequest $request): Response
    {
        $this->taskLists->reorder($request->user(), $request->folderId(), $request->taskListIds());

        return response()->noContent();
    }
}
