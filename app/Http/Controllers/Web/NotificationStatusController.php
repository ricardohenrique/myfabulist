<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationStatusController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notifications,
    ) {}

    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $validated = $request->validate(['read' => ['required', 'boolean']]);
        $record = $this->notifications->findForUser($request->user(), $notification);

        $this->notifications->setReadState($record, $validated['read']);

        return back();
    }
}
