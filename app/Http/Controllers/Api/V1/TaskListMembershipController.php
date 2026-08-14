<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaskList;
use App\Services\ListSharingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: a member leaving a
 * shared list voluntarily. Kept separate from `TaskListMemberController`
 * (which is owner-only roster management) — leaving is the caller acting on
 * their own membership, not on someone else's (Q2).
 */
class TaskListMembershipController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
    ) {}

    /**
     * `leave()` (`TaskListPolicy`) already excludes the owner — there is no
     * ownership-transfer mechanism in v1 (Q6), so the owner is denied here
     * with a 403 rather than reaching `ListSharingService::leave()`'s own
     * 422 `OwnerMembershipCannotBeRemovedException` re-check.
     */
    public function destroy(Request $request, TaskList $list): Response
    {
        $this->authorize('leave', $list);

        $this->sharing->leave($list, $request->user());

        return response()->noContent();
    }
}
