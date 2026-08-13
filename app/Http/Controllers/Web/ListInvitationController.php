<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RespondToInvitationRequest;
use App\Models\TaskListMember;
use App\Services\ListSharingService;
use Illuminate\Http\RedirectResponse;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 8: the web mirror of
 * `Api\V1\ListInvitationController` — accept/decline over Inertia web
 * routes. No `index()` here: the notification center (Step 9) reads pending
 * invitations through `HandleInertiaRequests::share()`'s shared
 * `notifications` prop, not a dedicated page route.
 */
class ListInvitationController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
    ) {}

    public function accept(RespondToInvitationRequest $request, TaskListMember $invitation): RedirectResponse
    {
        $this->sharing->accept($invitation, $request->user());

        return back()->with('success', 'Invitation accepted.');
    }

    public function decline(RespondToInvitationRequest $request, TaskListMember $invitation): RedirectResponse
    {
        $this->sharing->decline($invitation, $request->user());

        return back()->with('success', 'Invitation declined.');
    }
}
