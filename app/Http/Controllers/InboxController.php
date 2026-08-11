<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\WorkspacePresenter;
use App\Services\TaskListService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskListService,
        private readonly WorkspacePresenter $workspace,
    ) {}

    /**
     * Display the authenticated user's inbox.
     *
     * Resolves the Inbox through the exact same service method the API's
     * InboxController calls, in-process (D1) — this is the demonstration
     * of the single-application-layer guarantee.
     */
    public function __invoke(Request $request): Response
    {
        $inbox = $this->taskListService->inboxFor($request->user());

        return Inertia::render('workspace/show', [
            'workspace' => $this->workspace->forList($request->user(), $inbox),
        ]);
    }
}
