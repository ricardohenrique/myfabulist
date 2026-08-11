<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\WorkspacePresenter;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskListController extends Controller
{
    public function __construct(
        private readonly WorkspacePresenter $workspace,
    ) {}

    /**
     * Display a single list's task panel at its own URL (A3). The Inbox has
     * its own canonical /inbox route, so its id redirects there instead of
     * rendering a second URL for the same list.
     */
    public function __invoke(Request $request, TaskList $list): Response|RedirectResponse
    {
        $this->authorize('view', $list);

        if ($list->is_default) {
            return redirect()->route('inbox');
        }

        $list->load('folder');

        return Inertia::render('workspace/show', [
            'workspace' => $this->workspace->forList($request->user(), $list),
        ]);
    }
}
