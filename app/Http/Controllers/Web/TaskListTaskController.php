<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskRequest;
use App\Models\TaskList;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class TaskListTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function store(StoreTaskRequest $request, TaskList $list): RedirectResponse
    {
        $this->tasks->create($request->user(), $list, $request->validated('title'));

        return back()->with('success', 'Task added.');
    }
}
