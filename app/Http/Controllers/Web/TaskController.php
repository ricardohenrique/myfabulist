<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateTaskRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly UpdateTask $updateTask,
    ) {}

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->updateTask->handle(
            $task,
            $request->user(),
            $request->toData(),
            $request->targetListId(),
        );

        return back()->with('success', 'Task details saved.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $this->tasks->delete($task);

        return back()->with('success', 'Task deleted.');
    }
}
