<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateTaskListOrderRequest;
use App\Services\TaskListService;
use Illuminate\Http\RedirectResponse;

class TaskListOrderController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskLists,
    ) {}

    public function __invoke(UpdateTaskListOrderRequest $request): RedirectResponse
    {
        $this->taskLists->reorder($request->user(), $request->folderId(), $request->taskListIds());

        return back()->with('success', 'List order updated.');
    }
}
