<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class CompleteTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->tasks->complete($task);

        return back()->with('success', 'Task completed.');
    }
}
