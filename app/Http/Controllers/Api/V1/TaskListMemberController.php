<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyTaskListMemberRequest;
use App\Http\Requests\Api\V1\StoreTaskListInvitationRequest;
use App\Http\Resources\Api\V1\TaskListMemberResource;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Services\ListSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: the member roster for a
 * single list, plus invite/revoke.
 */
class TaskListMemberController extends Controller
{
    public function __construct(
        private readonly ListSharingService $sharing,
        private readonly TaskListMemberRepositoryInterface $members,
    ) {}

    /**
     * Any accepted member sees the roster (F18) — not `manageMembers`,
     * which is owner-only and gates the mutating actions below.
     */
    public function index(TaskList $list): JsonResponse
    {
        $this->authorize('view', $list);

        return TaskListMemberResource::collection($this->members->acceptedMembersFor($list))->response();
    }

    public function store(StoreTaskListInvitationRequest $request, TaskList $list): JsonResponse
    {
        $membership = $this->sharing->invite($list, $request->user(), $request->validated('email'));

        // $list is already the exact TaskList this membership belongs to —
        // attach it as the loaded relation rather than triggering a second
        // query for something already in hand. `user` still needs an
        // explicit eager load (loadMissing(), not the bare accessor —
        // Model::preventLazyLoading() forbids the latter, mirroring
        // ListSharingService::accept()'s own loadMissing('taskList') for the
        // same reason): TaskListMemberRepositoryInterface::create() returns
        // the membership row without it.
        return TaskListMemberResource::make(
            $membership->setRelation('taskList', $list)->loadMissing('user')
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(DestroyTaskListMemberRequest $request, TaskList $list, User $user): Response
    {
        $this->sharing->revoke($list, $request->user(), $user);

        return response()->noContent();
    }
}
