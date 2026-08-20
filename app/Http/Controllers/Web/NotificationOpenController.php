<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationOpenController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notifications,
    ) {}

    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $record = $this->notifications->findForUser($request->user(), $notification);
        $item = $this->notifications->itemFor($request->user(), $record);

        $this->notifications->setReadState($record, true);

        return $item['targetUrl'] === null
            ? redirect()->route('notifications.index')
            : redirect()->to($item['targetUrl']);
    }
}
