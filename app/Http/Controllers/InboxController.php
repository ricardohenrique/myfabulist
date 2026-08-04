<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TaskListService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct(
        private readonly TaskListService $taskListService,
    ) {}

    /**
     * Display the authenticated user's inbox.
     *
     * Resolves the Inbox through the exact same service method the API's
     * InboxController calls, in-process (D1) — this is the demonstration
     * of the single-application-layer guarantee.
     */
    public function __invoke(Request $request): View
    {
        $inbox = $this->taskListService->inboxFor($request->user());

        return view('inbox', ['taskListId' => $inbox->id]);
    }
}
