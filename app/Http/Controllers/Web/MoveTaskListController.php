<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\MoveTaskListRequest;
use App\Models\TaskList;
use App\Services\TaskListService;
use Illuminate\Http\RedirectResponse;

class MoveTaskListController extends Controller
{
    public function __construct(private readonly TaskListService $taskLists) {}

    public function __invoke(MoveTaskListRequest $request, TaskList $list): RedirectResponse
    {
        $this->taskLists->move($list, $request->user(), $request->folderId());

        return back()->with('success', 'List moved.');
    }
}
