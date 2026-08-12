<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AlreadyMemberException;
use App\Exceptions\CannotInviteSelfException;
use App\Exceptions\InvitationNoLongerPendingException;
use App\Exceptions\NotAMemberException;
use App\Exceptions\NotListOwnerException;
use App\Exceptions\OwnerMembershipCannotBeRemovedException;
use App\Exceptions\TaskListCannotBeSharedException;
use App\Exceptions\TaskListMemberLimitReachedException;
use App\Exceptions\UserNotFoundForInvitationException;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 5: the full sharing
 * lifecycle — invite, accept, decline, revoke, leave. Service-level only, no
 * HTTP-layer callers yet (Steps 7-8 wire this up over `/api/v1` and
 * `routes/web.php`).
 *
 * Every method that checks "is the caller entitled to act on this row/list"
 * does so itself, even though `TaskListPolicy` also gates the same rule at
 * the HTTP layer — defence in depth, matching
 * `EloquentTaskListRepository::update()`'s own accepted-membership
 * re-check and `TaskListService::delete()`'s duplicated `is_default` guard.
 * Every such check throws a 422 `DomainException`, never a 403 — this
 * Service has no way to know whether a caller reached it through a route
 * that already ran the Policy or a future caller that didn't, so a single,
 * consistent status code for "you can't do that" is what it can promise.
 */
class ListSharingService
{
    public function __construct(
        private readonly TaskListMemberRepositoryInterface $members,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Invite a user by email to an accepted membership on $taskList.
     * Rejects the Inbox (F9), self-invite, an invitee who is already an
     * accepted member, and a list already at the member cap. A duplicate
     * pending invite is idempotent and skips the cap check entirely — it
     * didn't fail the cap the first time, so re-submitting the same invite
     * must not newly fail it. Re-inviting a previously declined user flips
     * their existing row back to `pending` (Q10(d)).
     */
    public function invite(TaskList $taskList, User $inviter, string $email): TaskListMember
    {
        if ($taskList->is_default) {
            throw TaskListCannotBeSharedException::becauseItIsTheDefaultList($taskList);
        }

        $normalizedEmail = Str::lower(trim($email));
        $invitee = $this->users->findByEmail($normalizedEmail)
            ?? throw UserNotFoundForInvitationException::forEmail($normalizedEmail);

        if ($invitee->id === $inviter->id) {
            throw CannotInviteSelfException::for($inviter);
        }

        $existing = $this->members->findMembership($taskList, $invitee);

        if ($existing?->status === 'accepted') {
            throw AlreadyMemberException::for($taskList, $invitee);
        }

        if ($existing?->status !== 'pending' && $this->atMemberCap($taskList)) {
            throw TaskListMemberLimitReachedException::for($taskList);
        }

        // A single upsert write — no transaction needed here, `create()` is
        // already atomic as one statement.
        return $this->members->create($taskList, $invitee, $inviter);
    }

    /**
     * Accept an invitation. An already-accepted membership is treated as an
     * idempotent no-op rather than an error — a double-submit or a
     * double-click on the same "Accept" button is a normal client-side
     * event, not a business-rule violation, and there is nothing left to do
     * (the row is already in its target state). A `declined` row, by
     * contrast, throws `InvitationNoLongerPendingException`: it is not a
     * live invitation any more, and accepting it would silently resurrect a
     * decision the user already made — a fresh `invite()` call is required
     * to make it live again.
     *
     * Position is computed *before* handing off to the repository:
     * `nextPositionFor()` only counts `status = 'accepted'` rows, so while
     * this row is still `pending` it correctly excludes itself from its own
     * position calculation. Computing it after the status flip would count
     * this same row as one of "the user's existing accepted memberships",
     * producing an off-by-one — every accept after the first would land one
     * slot too high, and on a user's very first accepted shared list it
     * would compute position 1 instead of 0, leaving slot 0 permanently
     * empty in that user's ordering.
     *
     * The status flip, placement, and member-cap re-check are one atomic,
     * lock-guarded repository call (`acceptInvitation()`) rather than a
     * Service-level transaction wrapping separate calls — a plain,
     * non-locking count re-check is not actually race-safe against two
     * concurrent accepts on the same list (see the repository interface
     * docblock), so the lock has to live where the write does.
     */
    public function accept(TaskListMember $membership, User $user): TaskListMember
    {
        // loadMissing(), not the bare $membership->taskList accessor — a
        // caller may hand this method a TaskListMember that never eager-
        // loaded taskList (e.g. straight from findMembership()), and
        // Model::preventLazyLoading() (AppServiceProvider) throws outside
        // production if a relation is lazy-loaded. Mirrors TaskPolicy's own
        // ->loadMissing('taskList') for the same reason.
        $taskList = $membership->loadMissing('taskList')->taskList;

        if ($membership->user_id !== $user->id) {
            throw NotAMemberException::for($taskList, $user);
        }

        if ($membership->status === 'accepted') {
            return $membership;
        }

        if ($membership->status !== 'pending') {
            throw InvitationNoLongerPendingException::for($taskList, $user);
        }

        $position = $this->members->nextPositionFor($user, null);

        return $this->members->acceptInvitation($membership, $position, (int) config('sharing.max_members_per_list'));
    }

    /**
     * Decline an invitation. A `pending` row transitions to `declined`; an
     * already-`declined` row is an idempotent no-op (declining twice is not
     * an error — it is just another identical write). An `accepted` row,
     * however, is not declinable — most importantly, this is what stops
     * `decline()` from becoming a third, unguarded way to strip the owner's
     * own (always-`accepted`) membership, alongside `leave()` and
     * `revoke()`, which both correctly reject that via
     * `OwnerMembershipCannotBeRemovedException`. Declining an `accepted`
     * row throws `InvitationNoLongerPendingException` — it is not a live
     * invitation any more, regardless of who holds it.
     */
    public function decline(TaskListMember $membership, User $user): TaskListMember
    {
        $taskList = $membership->loadMissing('taskList')->taskList;

        if ($membership->user_id !== $user->id) {
            throw NotAMemberException::for($taskList, $user);
        }

        if ($membership->status === 'accepted') {
            throw InvitationNoLongerPendingException::for($taskList, $user);
        }

        return $this->members->updateStatus($membership, 'declined');
    }

    /**
     * Remove $member's membership on $taskList. $actor must own the list —
     * re-checked here as defence in depth even though
     * `TaskListPolicy::manageMembers()` already gates this at the HTTP
     * layer (see the class docblock). The owner's own row can never be the
     * target: it can only ever be removed by deleting the whole list.
     */
    public function revoke(TaskList $taskList, User $actor, User $member): void
    {
        if ($actor->id !== $taskList->user_id) {
            throw NotListOwnerException::for($taskList, $actor);
        }

        // A single delete write — no transaction needed for one statement.
        $membership = $this->members->findMembership($taskList, $member)
            ?? throw NotAMemberException::for($taskList, $member);

        if ($membership->user_id === $taskList->user_id) {
            throw OwnerMembershipCannotBeRemovedException::becauseTheyAreBeingRevoked($taskList);
        }

        $this->members->delete($membership);
    }

    /**
     * Leave a list voluntarily. The owner has no "leave" in v1 (Q6) — there
     * is no ownership-transfer mechanism, so their only way out is deleting
     * the list outright, which removes it for every member.
     */
    public function leave(TaskList $taskList, User $user): void
    {
        if ($user->id === $taskList->user_id) {
            throw OwnerMembershipCannotBeRemovedException::becauseTheyAreLeaving($taskList);
        }

        $membership = $this->members->findMembership($taskList, $user)
            ?? throw NotAMemberException::for($taskList, $user);

        $this->members->delete($membership);
    }

    private function atMemberCap(TaskList $taskList): bool
    {
        return $this->members->countAcceptedFor($taskList) >= config('sharing.max_members_per_list');
    }
}
