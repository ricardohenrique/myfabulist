<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskListRequest;
use App\Http\Requests\Web\UpdateTaskListRequest;
use App\Models\TaskList;
use App\Services\TaskListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskLists,
    ) {}

    public function store(StoreTaskListRequest $request): RedirectResponse
    {
        $list = $this->taskLists->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('folder_id'),
        );

        return redirect()->route('lists.show', $list)->with('success', "List “{$list->name}” created.");
    }

    public function update(UpdateTaskListRequest $request, TaskList $list): RedirectResponse
    {
        $list = $this->taskLists->update(
            $list,
            $request->user(),
            $request->validated('name'),
            $request->validated('folder_id'),
        );

        return back()->with('success', "List “{$list->name}” updated.");
    }

    public function destroy(Request $request, TaskList $list): RedirectResponse
    {
        $this->authorize('delete', $list);
        $this->taskLists->delete($list, $request->user());

        return redirect()->route('inbox')->with('success', "List “{$list->name}” deleted.");
    }
}
