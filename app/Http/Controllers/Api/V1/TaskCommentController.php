<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskCommentRequest;
use App\Http\Resources\Api\V1\TaskCommentResource;
use App\Models\Task;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly TaskCommentService $comments,
    ) {}

    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return TaskCommentResource::collection($this->comments->forTask($task))->response();
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->comments->create($task, $request->user(), $request->validated('body'));

        return TaskCommentResource::make($comment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
