<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TaskListMemberRepositoryInterface
{
    /**
     * Find the membership row (any status) linking this list and user.
     * Returns null when the user has no membership row for the list at all.
     */
    public function findMembership(TaskList $taskList, User $user): ?TaskListMember;

    /**
     * Every accepted member of the list, with `user` eager-loaded.
     *
     * @return Collection<int, TaskListMember>
     */
    public function acceptedMembersFor(TaskList $taskList): Collection;

    /**
     * This user's pending invitations, with `taskList` and `invitedBy`
     * eager-loaded. Excludes invitations to a since soft-deleted list.
     *
     * @return Collection<int, TaskListMember>
     */
    public function pendingFor(User $user): Collection;

    public function pendingCountFor(User $user): int;

    /**
     * Create the single accepted-owner membership row for a newly created
     * list. `$folderId`/`$position` are passed explicitly by the caller
     * (Plan 1, Step 2) rather than read off `$taskList` — `task_lists` no
     * longer carries placement columns at all, so there is nothing to read.
     */
    public function createOwnerMembership(TaskList $taskList, ?int $folderId, int $position): TaskListMember;

    /**
     * Create a pending invitation row for $user on $taskList, attributed to
     * $invitedBy. Not called by anything yet in Step 1 — the sharing
     * lifecycle (Step 5) is its first caller.
     */
    public function create(TaskList $taskList, User $user, User $invitedBy): TaskListMember;

    /**
     * Transition the membership to $status, stamping `responded_at = now()`
     * as part of the same write — a status transition is the "responded"
     * event, so callers never need a second call to keep the two in sync.
     */
    public function updateStatus(TaskListMember $member, string $status): TaskListMember;

    public function updatePlacement(TaskListMember $member, ?int $folderId, int $position): TaskListMember;

    public function delete(TaskListMember $member): void;

    /**
     * Mirrors `TaskListRepositoryInterface::nextPosition`, scoped to this
     * user's accepted membership rows (excluding pending invites and
     * memberships of a soft-deleted list) instead of `task_lists`.
     */
    public function nextPositionFor(User $user, ?int $folderId): int;

    public function countAcceptedFor(TaskList $taskList): int;
}
