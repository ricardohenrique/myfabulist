<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\TaskCannotBeUndeletedException;
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
