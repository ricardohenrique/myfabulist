<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SetTaskStarredRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class StarTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(SetTaskStarredRequest $request, Task $task): RedirectResponse
    {
        $isStarred = $request->isStarred();
        $this->tasks->setStarred($task, $isStarred);

        return back()->with('success', $isStarred ? 'Task starred.' : 'Task unstarred.');
    }
}
