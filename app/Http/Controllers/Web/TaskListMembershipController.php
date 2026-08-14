<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TaskList;
use App\Services\ListSharingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 8: the web mirror of
 * `Api\V1\TaskListMembershipController` — a member leaving a shared list
 * voluntarily. Redirects to `inbox`, not `back()`: the moment this succeeds
 * the acting user loses access to `/lists/{list}` (`TaskListPolicy::view()`),
 * so `back()` would bounce them straight into a 403 if they were on the
 * list's own page — mirrors `Web\TaskListController::destroy()`'s own
 * redirect-to-inbox-after-delete for the same reason.
 */
class TaskListMembershipController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
    ) {}

    public function destroy(Request $request, TaskList $list): RedirectResponse
    {
        $this->authorize('leave', $list);

        $this->sharing->leave($list, $request->user());

        return redirect()->route('inbox')->with('success', "You left \"{$list->name}\".");
    }
}
