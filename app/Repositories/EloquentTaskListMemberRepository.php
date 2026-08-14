<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\TaskListMemberLimitReachedException;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTaskListMemberRepository implements TaskListMemberRepositoryInterface
{
    public function findMembership(TaskList $taskList, User $user): ?TaskListMember
    {
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function findForRouteBinding(int $id): ?TaskListMember
    {
        return TaskListMember::query()
            ->whereHas('taskList')
            ->with('taskList')
            ->find($id);
    }

    /**
     * @return Collection<int, TaskListMember>
     */
    public function acceptedMembersFor(TaskList $taskList): Collection
    {
        // $taskList is already the exact instance every returned row
        // belongs to — attach it as each row's `taskList` relation instead
        // of an eager-loaded `with('taskList')`, which would re-query for
        // something already in hand.
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('status', 'accepted')
            ->with('user')
            ->orderBy('id')
            ->get()
            ->each(fn (TaskListMember $member) => $member->setRelation('taskList', $taskList));
    }

    /**
     * @return Collection<int, TaskListMember>
     */
    public function pendingFor(User $user): Collection
    {
        return TaskListMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            // A pending invitation to a since-deleted list must not hydrate
            // `taskList` as null — SoftDeletes' global scope makes this an
            // existence filter, not just an eager-load guard.
            ->whereHas('taskList')
            ->with(['taskList', 'invitedBy'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TaskListMember>
     */
    public function pendingInvitationsFor(TaskList $taskList): Collection
    {
        // Mirrors acceptedMembersFor() above exactly, filtered to
        // status = 'pending' — see the interface docblock.
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('status', 'pending')
            ->with('user')
            ->orderBy('id')
            ->get()
            ->each(fn (TaskListMember $member) => $member->setRelation('taskList', $taskList));
    }

    public function pendingCountFor(User $user): int
    {
        // Same whereHas('taskList') guard as pendingFor() above, for the
        // same reason: without it, this count would include invitations to
        // a since-deleted list that GET /invitations (pendingFor()) itself
        // already excludes — Step 8's bell badge would show a number that
        // doesn't match the list underneath it.
        return TaskListMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('taskList')
            ->count();
    }

    public function createOwnerMembership(TaskList $taskList, ?int $folderId, int $position): TaskListMember
    {
        $member = new TaskListMember;

        $member->forceFill([
            'task_list_id' => $taskList->id,
            'user_id' => $taskList->user_id,
            'status' => 'accepted',
            'folder_id' => $folderId,
            'position' => $position,
            'invited_by_user_id' => null,
            'invited_at' => null,
            'responded_at' => now(),
        ])->save();

        return $member;
    }

    public function create(TaskList $taskList, User $user, User $invitedBy): TaskListMember
    {
        // Find-then-branch rather than a single upsert query: the three
        // branches have materially different writes (a fresh insert; an
        // update that also clears responded_at; a genuine no-op that must
        // return the existing row untouched), and TaskListMember has no
        // natural single-statement "upsert with conditional columns"
        // expression that reads as clearly as this does. The unique
        // (task_list_id, user_id) constraint is still the real guarantee
        // against a duplicate row — this method just decides which of the
        // three valid outcomes applies before writing.
        $existing = $this->findMembership($taskList, $user);

        if ($existing === null) {
            $member = new TaskListMember;

            $member->forceFill([
                'task_list_id' => $taskList->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'folder_id' => null,
                'position' => 0,
                'invited_by_user_id' => $invitedBy->id,
                'invited_at' => now(),
                'responded_at' => null,
            ])->save();

            return $member;
        }

        if ($existing->status === 'declined') {
            $existing->forceFill([
                'status' => 'pending',
                'invited_by_user_id' => $invitedBy->id,
                'invited_at' => now(),
                'responded_at' => null,
            ])->save();
        }

        // A 'pending' row is returned as-is — the idempotent duplicate-invite
        // case. An 'accepted' row should never reach here: the Service
        // rejects that case (AlreadyMemberException) before ever calling
        // create().
        return $existing;
    }

    public function updateStatus(TaskListMember $member, string $status): TaskListMember
    {
        // A status transition *is* the "responded" event (accept/decline),
        // so it stamps responded_at itself — callers never need a second
        // repository call to keep the two in sync.
        $member->forceFill([
            'status' => $status,
            'responded_at' => now(),
        ])->save();

        return $member;
    }

    public function updatePlacement(TaskListMember $member, ?int $folderId, int $position): TaskListMember
    {
        $member->forceFill([
            'folder_id' => $folderId,
            'position' => $position,
        ])->save();

        return $member;
    }

    public function delete(TaskListMember $member): void
    {
        $member->delete();
    }

    public function nextPositionFor(User $user, ?int $folderId): int
    {
        // Positions are only meaningful for accepted rows — a pending
        // invite's placeholder position=0 must never contribute to a real
        // slot — and whereHas('taskList') excludes memberships of a
        // soft-deleted list via SoftDeletes' global scope, the same way
        // EloquentTaskListRepository::nextPosition() gets that exclusion
        // for free from TaskList::query().
        $maxPosition = TaskListMember::query()
            ->where('user_id', $user->id)
            ->where('folder_id', $folderId)
            ->where('status', 'accepted')
            ->whereHas('taskList')
            ->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    public function countAcceptedFor(TaskList $taskList): int
    {
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('status', 'accepted')
            ->count();
    }

    public function acceptInvitation(TaskListMember $membership, int $position, int $maxMembers): TaskListMember
    {
        return DB::transaction(function () use ($membership, $position, $maxMembers): TaskListMember {
            // Locks every existing accepted row for this list — mirrors
            // EloquentTaskListRepository::applyOrder()/
            // EloquentFolderRepository::applyOrder()'s own locked-read-
            // then-write pattern. A concurrent acceptInvitation() call for
            // the same list blocks on this same locked read until this
            // transaction commits or rolls back, then re-reads the current
            // (post-commit) count rather than a stale snapshot — this is
            // what makes the cap check below race-safe (N5).
            $lockedAcceptedCount = TaskListMember::query()
                ->where('task_list_id', $membership->task_list_id)
                ->where('status', 'accepted')
                ->lockForUpdate()
                ->count();

            if ($lockedAcceptedCount >= $maxMembers) {
                throw TaskListMemberLimitReachedException::for($membership->loadMissing('taskList')->taskList);
            }

            $membership->forceFill([
                'status' => 'accepted',
                'responded_at' => now(),
                // F4: a newly accepted member always lands ungrouped, never
                // inheriting the sharer's folder.
                'folder_id' => null,
                'position' => $position,
            ])->save();

            return $membership;
        });
    }
}
