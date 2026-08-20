<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Services\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $unreadOnly = $request->string('filter')->toString() === 'unread';

        return NotificationResource::collection(
            $this->notifications->forUser($request->user(), $unreadOnly)
        )->response();
    }

    public function update(Request $request, string $notification): NotificationResource
    {
        $validated = $request->validate(['read' => ['required', 'boolean']]);
        $record = $this->notifications->findForUser($request->user(), $notification);
        $this->notifications->setReadState($record, $validated['read']);

        return NotificationResource::make($this->notifications->itemFor($request->user(), $record));
    }
}
