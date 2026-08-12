<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 4: authorization moves
 * from `$user->id === $taskList->user_id` to membership. `view()` is the
 * single predicate — "does an accepted membership row exist" — every other
 * ability that only needs "can this user see the list at all" delegates to
 * it, so there is exactly one definition of access (R2's mitigation).
 * `update()` is the one ability that changed from owner-only to any-member
 * (Q4); `delete()`, `share()`, and `manageMembers()` stay owner-only (Q2/Q6).
 *
 * `task_list_members` is the *sole* source of access truth here — `view()`
 * deliberately has no `|| $user->id === $taskList->user_id` ownership
 * fallback in case an owner's own membership row is ever missing, since that
 * would reintroduce the two-sources-of-truth problem Architecture Decision 1
 * eliminated. This relies on list creation always going through
 * `EloquentTaskListRepository::{create,createDefaultFor}()`, which guarantee
 * the owner's accepted membership row is written in the same transaction as
 * the list itself — never construct a `TaskList` any other way.
 */
class TaskListPolicy
{
    public function __construct(
        private readonly TaskListMemberRepositoryInterface $members,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaskList $taskList): bool
    {
        return $this->isAcceptedMember($user, $taskList);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Any accepted member may rename a shared list — the list name is
     * shared state on the single canonical row (Q4: "Yes, any.").
     */
    public function update(User $user, TaskList $taskList): bool
    {
        return $this->view($user, $taskList);
    }

    /**
     * Owner-only, and the default (Inbox) list can never be deleted (Q6:
     * only the owner may delete the list outright, which soft-deletes it
     * for every member). This is defence in depth behind
     * `TaskListService::delete()`, which throws
     * `DefaultTaskListCannotBeDeletedException` for the same rule (D5).
     */
    public function delete(User $user, TaskList $taskList): bool
    {
        if ($taskList->is_default) {
            return false;
        }

        return $user->id === $taskList->user_id;
    }

    /**
     * Invite a new member or revoke an existing one. Owner-only (Q2).
     */
    public function share(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }

    /**
     * Manage the member roster (list/remove members). Owner-only (Q2) —
     * kept as a distinct ability from `share()` even though both are
     * currently the same owner-only check, since Step 5's controllers may
     * want to authorize "view the roster" separately from "revoke a member"
     * later without collapsing back onto a single ability.
     */
    public function manageMembers(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }

    /**
     * Any accepted member may leave voluntarily — except the owner, who has
     * no "leave" in v1 (Q6): there is no ownership-transfer mechanism, so
     * the owner's only way out is `delete()`, which removes the list for
     * every member instead of just themselves.
     */
    public function leave(User $user, TaskList $taskList): bool
    {
        return $this->isAcceptedMember($user, $taskList) && $user->id !== $taskList->user_id;
    }

    private function isAcceptedMember(User $user, TaskList $taskList): bool
    {
        return $this->members->findMembership($taskList, $user)?->status === 'accepted';
    }
}
