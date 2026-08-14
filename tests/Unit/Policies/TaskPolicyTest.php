<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_may_view_update_and_delete_their_task(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertTrue($policy->view($owner, $task));
        $this->assertTrue($policy->update($owner, $task));
        $this->assertTrue($policy->comment($owner, $task));
        $this->assertTrue($policy->delete($owner, $task));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertFalse($policy->view($stranger, $task));
        $this->assertFalse($policy->update($stranger, $task));
        $this->assertFalse($policy->comment($stranger, $task));
        $this->assertFalse($policy->delete($stranger, $task));
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4: TaskPolicy
     * delegates entirely to list membership — a pending or declined
     * membership grants zero access to the list's tasks.
     */
    public function test_a_pending_or_declined_member_is_denied(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $declined = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();
        TaskListMember::factory()->forTaskList($list, $declined)->create(['status' => 'declined']);

        $this->assertFalse($policy->view($pending, $task));
        $this->assertFalse($policy->update($pending, $task));
        $this->assertFalse($policy->comment($pending, $task));
        $this->assertFalse($policy->delete($pending, $task));

        $this->assertFalse($policy->view($declined, $task));
        $this->assertFalse($policy->update($declined, $task));
    }

    /**
     * A shared list's tasks are fully collaborative for every accepted
     * member — no owner-only tier at the task level (Q4/the plan's
     * "TaskPolicy delegates to list access" instruction).
     */
    public function test_an_accepted_non_owner_member_may_view_update_comment_and_delete(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $this->assertTrue($policy->view($member, $task));
        $this->assertTrue($policy->update($member, $task));
        $this->assertTrue($policy->comment($member, $task));
        $this->assertTrue($policy->delete($member, $task));
    }

    public function test_a_non_member_is_denied_regardless_of_who_created_the_task(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertFalse($policy->view($stranger, $task));
        $this->assertFalse($policy->update($stranger, $task));
        $this->assertFalse($policy->comment($stranger, $task));
        $this->assertFalse($policy->delete($stranger, $task));
    }

    /**
     * Plan 1, Step 4 (code-review follow-up): pinned explicitly rather than
     * relying on "it happens to work today because of a framework internal".
     * Soft-deleting a list never touches its tasks (D2), so a task can
     * legitimately still resolve while its own `taskList` relation resolves
     * to null (TaskList's own SoftDeletes global scope). `TaskPolicy` must
     * deny in that case — even for the list's own owner — rather than
     * authorizing against a list that no longer effectively exists.
     */
    public function test_a_task_whose_list_is_soft_deleted_is_denied_even_for_the_owner(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $list->delete();

        $this->assertFalse($policy->view($owner, $task->fresh()));
        $this->assertFalse($policy->update($owner, $task->fresh()));
        $this->assertFalse($policy->delete($owner, $task->fresh()));
    }
}
