<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreSubtaskRequest;
use App\Models\Task;
use App\Services\SubtaskService;
use Illuminate\Http\RedirectResponse;

class TaskSubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
    ) {}

    public function store(StoreSubtaskRequest $request, Task $task): RedirectResponse
    {
        $this->subtasks->create($task, $request->validated('title'));

        return back()->with('success', 'Subtask added.');
    }
}
