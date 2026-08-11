<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\MoveTaskRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class MoveTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(MoveTaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->move($task, $request->user(), $request->targetListId(), $request->position());

        return back()->with('success', 'Task moved.');
    }
}
