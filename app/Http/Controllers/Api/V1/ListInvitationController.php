<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RespondToInvitationRequest;
use App\Http\Resources\Api\V1\ListInvitationResource;
use App\Http\Resources\Api\V1\TaskListMemberResource;
use App\Models\TaskListMember;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Services\ListSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: the authenticated
 * user's own pending invitations, and responding to one. `{invitation}`
 * binds implicitly to `TaskListMember` — Laravel resolves the model class
 * from `accept()`/`decline()`'s type-hinted parameter, matched by parameter
 * name against the route wildcard, not from the route segment's literal
 * text, so no custom `Route::bind()` is needed here.
 */
class ListInvitationController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
        private readonly TaskListMemberRepositoryInterface $members,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ListInvitationResource::collection($this->members->pendingFor($request->user()))->response();
    }

    /**
     * Returns a TaskListMemberResource, not a ListInvitationResource — once
     * responded to, the row is no longer meaningfully "an invitation", it is
     * the caller's own membership record.
     */
    public function accept(RespondToInvitationRequest $request, TaskListMember $invitation): JsonResponse
    {
        return TaskListMemberResource::make(
            $this->sharing->accept($invitation, $request->user())->setRelation('user', $request->user())
        )->response();
    }

    public function decline(RespondToInvitationRequest $request, TaskListMember $invitation): JsonResponse
    {
        return TaskListMemberResource::make(
            $this->sharing->decline($invitation, $request->user())->setRelation('user', $request->user())
        )->response();
    }
}
