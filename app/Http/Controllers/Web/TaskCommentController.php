<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskCommentRequest;
use App\Models\Task;
use App\Services\TaskCommentService;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly TaskCommentService $comments,
    ) {}

    public function store(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->comments->create($task, $request->user(), $request->validated('body'));

        return back();
    }
}
