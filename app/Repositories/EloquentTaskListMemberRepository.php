<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentTaskListMemberRepository implements TaskListMemberRepositoryInterface
{
    public function findMembership(TaskList $taskList, User $user): ?TaskListMember
    {
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return Collection<int, TaskListMember>
     */
    public function acceptedMembersFor(TaskList $taskList): Collection
    {
        return TaskListMember::query()
            ->where('task_list_id', $taskList->id)
            ->where('status', 'accepted')
            ->with('user')
            ->orderBy('id')
            ->get();
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

    public function pendingCountFor(User $user): int
    {
        return TaskListMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
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
}
