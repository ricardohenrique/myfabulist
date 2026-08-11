<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateTaskOrderRequest;
use App\Models\TaskList;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class TaskOrderController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(UpdateTaskOrderRequest $request, TaskList $list): RedirectResponse
    {
        $this->tasks->reorder($list, $request->taskIds());

        return back()->with('success', 'Task order updated.');
    }
}
