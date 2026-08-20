<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\WorkspacePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly WorkspacePresenter $workspace,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('workspace/show', [
            'workspace' => $this->workspace->forNotifications($request->user()),
        ]);
    }
}
