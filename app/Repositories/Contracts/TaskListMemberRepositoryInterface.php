<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Exceptions\TaskListMemberLimitReachedException;
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
     * Resolve an invitation/membership by id for implicit route-model-
     * binding (`App\Providers\AppServiceProvider::configureRouteBindings()`),
     * requiring its list to still exist — `whereHas('taskList')` excludes a
     * row whose list has since been soft-deleted (a legitimate invitee can
     * still hold a `pending` row for a list the owner deleted in the
     * meantime), mirroring `pendingFor()`'s own guard below for the same
     * reason. `taskList` is always eager-loaded here so no downstream caller
     * (`ListSharingService`, `TaskListMemberResource`) can ever dereference a
     * null relation. Not scoped to any particular viewer on purpose — this
     * only decides the target (together with a still-existing list) exists
     * at all; `TaskListMemberPolicy::respond()` decides who may act on it.
     */
    public function findForRouteBinding(int $id): ?TaskListMember;

    /**
     * Every accepted member of the list, with `user` and `taskList`
     * eager-loaded — `TaskListMemberResource` needs the list's `user_id` to
     * decide email visibility and the `is_owner` flag for each row (Plan 1,
     * Step 7).
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

    /**
     * Excludes invitations to a since soft-deleted list, same as
     * `pendingFor()` — the bell notification badge count (Step 8) must never
     * disagree with `pendingFor()`'s own list.
     */
    public function pendingCountFor(User $user): int;

    /**
     * Create the single accepted-owner membership row for a newly created
     * list. `$folderId`/`$position` are passed explicitly by the caller
     * (Plan 1, Step 2) rather than read off `$taskList` — `task_lists` no
     * longer carries placement columns at all, so there is nothing to read.
     */
    public function createOwnerMembership(TaskList $taskList, ?int $folderId, int $position): TaskListMember;

    /**
     * The single entry point `ListSharingService::invite()` calls regardless
     * of whether this is a fresh invite or a re-invite after a decline — an
     * upsert on the unique `(task_list_id, user_id)` pair (Plan 1, Step 5):
     *
     * - No existing row for this pair → insert a fresh `pending` row,
     *   attributed to `$invitedBy`.
     * - Existing `declined` row → update it back to `pending`, refreshing
     *   `invited_by_user_id`/`invited_at` and clearing `responded_at` — it is
     *   an open invitation again, not yet responded to.
     * - Existing `pending` row → idempotent no-op; the existing row is
     *   returned as-is (a duplicate pending invite is not an error).
     *
     * This is a pure upsert primitive — whether inviting is even *allowed*
     * (member cap, self-invite, already-accepted, Inbox) is a decision the
     * Service makes before ever calling this method.
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

    /**
     * Atomically accept a pending invitation: locks $membership's list's
     * `accepted` membership rows (`lockForUpdate()`, mirroring
     * `EloquentTaskListRepository::applyOrder()`/`EloquentFolderRepository::applyOrder()`'s
     * own locked-read-then-write pattern), re-checks the cap against that
     * locked, up-to-date count, then flips $membership to `accepted` and
     * places it at $position (F4: always ungrouped, `folder_id = null`).
     *
     * This is the race-safe replacement for a plain, non-locking
     * `countAcceptedFor()` check followed by separate `updateStatus()` +
     * `updatePlacement()` calls: two concurrent accepts on the same list
     * would otherwise both read the same pre-write count and both pass a
     * cap check that should only have let one of them through (N5). The
     * lock serializes them — the second call's locked read blocks until the
     * first transaction commits, then re-reads the now-current count.
     *
     * @throws TaskListMemberLimitReachedException when the locked count is
     *                                             already >= $maxMembers.
     */
    public function acceptInvitation(TaskListMember $membership, int $position, int $maxMembers): TaskListMember;
}
