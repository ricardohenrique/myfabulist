<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TaskList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TaskListController extends Controller
{
    /**
     * Display a single list's task panel at its own URL (A3). The Inbox has
     * its own canonical /inbox route, so its id redirects there instead of
     * rendering a second URL for the same list.
     */
    public function __invoke(TaskList $list): View|RedirectResponse
    {
        $this->authorize('view', $list);

        if ($list->is_default) {
            return redirect()->route('inbox');
        }

        return view('lists.show', ['taskList' => $list]);
    }
}
