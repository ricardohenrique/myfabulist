<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DestroyTaskListMemberRequest;
use App\Http\Requests\Web\StoreTaskListInvitationRequest;
use App\Models\TaskList;
use App\Models\User;
use App\Services\ListSharingService;
use Illuminate\Http\RedirectResponse;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 8: the web mirror of
 * `Api\V1\TaskListMemberController` — invite/revoke over Inertia web routes,
 * returning `back()` with flash instead of a JSON envelope. No `index()`
 * here: the web frontend reads the member roster through
 * `WorkspacePresenter::forList()`'s `currentList.members`, not a dedicated
 * page route (matching how comments/subtasks already work on the web side).
 */
class TaskListMemberController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
    ) {}

    public function store(StoreTaskListInvitationRequest $request, TaskList $list): RedirectResponse
    {
        $this->sharing->invite($list, $request->user(), $request->validated('email'));

        return back()->with('success', 'Invitation sent.');
    }

    public function destroy(DestroyTaskListMemberRequest $request, TaskList $list, User $user): RedirectResponse
    {
        $this->sharing->revoke($list, $request->user(), $user);

        return back()->with('success', 'Member removed.');
    }
}
