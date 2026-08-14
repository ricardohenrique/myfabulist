<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\TaskCannotBeUndeletedException;
use App\Exceptions\TaskCannotCrossSharingBoundaryException;
use App\Exceptions\TaskListNotFoundException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan 4/D3: the two behavioural guard tests that pin the
 * `restore()` (un-complete) vs `undelete()` (un-delete) distinction, so a
 * future contributor can never accidentally make one do the other's job.
 * Plan 4/D6: the "list is deleted" refusal on task undelete.
 */
class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_does_not_clear_deleted_at_on_a_trashed_task(): void
    {
        $task = Task::factory()->completed()->create();
        $task->delete();
        $service = app(TaskService::class);

        $service->restore($task);

        $this->assertNotNull($task->fresh()->deleted_at);
    }

    public function test_undelete_does_not_alter_is_completed_or_completed_at(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->completed()->create();
        $completedAt = $task->completed_at;
        $task->delete();
        $service = app(TaskService::class);

        $undeleted = $service->undelete($task, $user);

        $this->assertTrue($undeleted->is_completed);
        $this->assertTrue($undeleted->completed_at->equalTo($completedAt));
        $this->assertNull($undeleted->deleted_at);
        $this->assertTrue($service->tasksFor($list, $user)->completed->contains(fn (Task $t): bool => $t->is($task)));
    }

    public function test_undeleting_a_task_twice_is_harmless(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $task->delete();
        $service = app(TaskService::class);

        $service->undelete($task, $user);
        $service->undelete($task, $user);

        $this->assertNull($task->fresh()->deleted_at);
    }

    /**
     * R4/D2 defence in depth, service layer: findOwnedBy() is trashed-blind,
     * so a deleted target list resolves to null exactly like a foreign one —
     * even a validation bypass cannot move a task into a deleted list.
     */
    public function test_move_to_a_deleted_list_throws_even_when_called_directly(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $deletedList = TaskList::factory()->create(['user_id' => $user->id]);
        $deletedList->delete();
        $task = Task::factory()->forTaskList($sourceList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskListNotFoundException::class);

        $service->move($task, $user, $deletedList->id, null);
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4/Q8: the regression
     * test proving the findOwnedBy()/findAccessibleFor() split actually
     * preserved the move() boundary rather than accidentally widening it —
     * an accepted (non-owner) membership on some *other* list must never
     * make that list a valid move target, even though the same acting user
     * can otherwise fully view/update tasks in it (Step 4's own
     * TaskPolicy/TaskListPolicy widening). Only the user's own lists are
     * ever valid move targets.
     */
    public function test_move_rejects_a_list_the_user_has_accepted_membership_on_but_does_not_own(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $sharedList = TaskList::factory()->create(['user_id' => $otherOwner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $user)->create();
        $task = Task::factory()->forTaskList($sourceList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskListNotFoundException::class);

        try {
            $service->move($task, $user, $sharedList->id, null);
        } finally {
            $this->assertSame($sourceList->id, $task->fresh()->task_list_id);
        }
    }

    /**
     * F15/Q8 (Plan 1, Step 6): ownership of the target list alone is not
     * enough — a non-owner, accepted member of a shared list must never be
     * able to move a task out of it into a private list they themselves
     * own. `findOwnedBy()` alone would let this through (the member really
     * does own their private list); the new sharing-boundary guard is what
     * actually stops it.
     */
    public function test_move_out_of_a_shared_list_into_the_movers_own_private_list_is_rejected_for_a_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $member)->create();
        $memberPrivateList = TaskList::factory()->create(['user_id' => $member->id]);
        $task = Task::factory()->forTaskList($sharedList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskCannotCrossSharingBoundaryException::class);

        try {
            $service->move($task, $member, $memberPrivateList->id, null);
        } finally {
            $this->assertSame($sharedList->id, $task->fresh()->task_list_id);
        }
    }

    /**
     * The same rule applies to the list's owner, not just a non-owner
     * member (Q8: "regardless of who initiates it") — the owner of a shared
     * list cannot move a task out of it into one of their own private
     * lists either.
     */
    public function test_move_out_of_a_shared_list_into_the_owners_own_private_list_is_rejected_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $member)->create();
        $ownerPrivateList = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($sharedList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskCannotCrossSharingBoundaryException::class);

        try {
            $service->move($task, $owner, $ownerPrivateList->id, null);
        } finally {
            $this->assertSame($sharedList->id, $task->fresh()->task_list_id);
        }
    }

    /**
     * The reverse direction: the owner of a shared list cannot move a task
     * from one of their own private lists into the shared one either.
     */
    public function test_move_into_a_shared_list_from_a_private_list_is_rejected_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $member)->create();
        $ownerPrivateList = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($ownerPrivateList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskCannotCrossSharingBoundaryException::class);

        try {
            $service->move($task, $owner, $sharedList->id, null);
        } finally {
            $this->assertSame($ownerPrivateList->id, $task->fresh()->task_list_id);
        }
    }

    /**
     * A non-owner member can never reach the sharing-boundary guard on this
     * direction at all — `findOwnedBy()` already rejects the shared list as
     * a target before the guard is ever evaluated, since the member does
     * not own it. This is the same invariant
     * `test_move_rejects_a_list_the_user_has_accepted_membership_on_but_does_not_own()`
     * above already pins; restated here to make the "into a shared list
     * from a private one, for both actor types" requirement explicit.
     */
    public function test_move_into_a_shared_list_from_a_private_list_is_rejected_for_a_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $member)->create();
        $memberPrivateList = TaskList::factory()->create(['user_id' => $member->id]);
        $task = Task::factory()->forTaskList($memberPrivateList)->create();
        $service = app(TaskService::class);

        $this->expectException(TaskListNotFoundException::class);

        try {
            $service->move($task, $member, $sharedList->id, null);
        } finally {
            $this->assertSame($memberPrivateList->id, $task->fresh()->task_list_id);
        }
    }

    /**
     * Regression: moving between two private lists both owned by the mover
     * — neither side has more than one accepted member — still works
     * exactly as before the sharing-boundary guard was added.
     */
    public function test_move_between_two_private_lists_owned_by_the_mover_still_works(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $targetList = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($sourceList)->create();
        $service = app(TaskService::class);

        $moved = $service->move($task, $user, $targetList->id, null);

        $this->assertSame($targetList->id, $moved->task_list_id);
    }

    public function test_user_can_delete_and_undelete_and_it_returns_to_its_original_position(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['position' => 2]);
        $service = app(TaskService::class);

        $service->delete($task);
        $service->undelete($task, $user);

        $this->assertTrue($service->tasksFor($list, $user)->active->contains(fn (Task $t): bool => $t->is($task) && $t->position === 2));
    }

    /**
     * D6's refusal: undeleting a task whose list is itself deleted must
     * throw, not silently succeed and hide the task, and must leave
     * `deleted_at` set.
     */
    public function test_undelete_throws_when_the_tasks_list_is_deleted(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $task->delete();
        $list->delete();
        $service = app(TaskService::class);

        $this->expectException(TaskCannotBeUndeletedException::class);

        try {
            $service->undelete($task, $user);
        } finally {
            $this->assertNotNull($task->fresh()->deleted_at);
        }
    }

    /**
     * Once the list itself is un-deleted, un-deleting the task it holds
     * succeeds again — the refusal is about the list's current state, not a
     * permanent lock.
     */
    public function test_undelete_succeeds_once_its_list_is_restored(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $task->delete();
        $list->delete();
        $service = app(TaskService::class);

        $list->restore();
        $undeleted = $service->undelete($task, $user);

        $this->assertNull($undeleted->deleted_at);
    }

    /**
     * Ownership scoping applies to the undelete lookup exactly as it does to
     * the live one (NFR: security) — a stranger's list must not satisfy the
     * D6 guard even though it is not itself deleted.
     */
    public function test_undelete_throws_when_the_tasks_list_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $task->delete();
        $service = app(TaskService::class);

        $this->expectException(TaskCannotBeUndeletedException::class);

        $service->undelete($task, $stranger);
    }
}
