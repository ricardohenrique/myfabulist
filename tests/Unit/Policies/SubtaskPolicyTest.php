<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Policies\SubtaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_task_owner_may_view_update_and_delete_a_subtask(): void
    {
        $policy = new SubtaskPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->assertTrue($policy->view($owner, $subtask));
        $this->assertTrue($policy->update($owner, $subtask));
        $this->assertTrue($policy->delete($owner, $subtask));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = new SubtaskPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->assertFalse($policy->view($stranger, $subtask));
        $this->assertFalse($policy->update($stranger, $subtask));
        $this->assertFalse($policy->delete($stranger, $subtask));
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4: SubtaskPolicy
     * delegates through Task to list membership — a pending or declined
     * membership grants zero access.
     */
    public function test_a_pending_or_declined_member_is_denied(): void
    {
        $policy = new SubtaskPolicy;
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();

        $this->assertFalse($policy->view($pending, $subtask));
        $this->assertFalse($policy->update($pending, $subtask));
        $this->assertFalse($policy->delete($pending, $subtask));
    }

    /**
     * An accepted non-owner list member may fully manage a subtask —
     * collaborative, no owner-only tier (mirrors TaskPolicy).
     */
    public function test_an_accepted_non_owner_member_may_view_update_and_delete(): void
    {
        $policy = new SubtaskPolicy;
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $this->assertTrue($policy->view($member, $subtask));
        $this->assertTrue($policy->update($member, $subtask));
        $this->assertTrue($policy->delete($member, $subtask));
    }

    /**
     * Plan 1, Step 4 (code-review follow-up): mirrors TaskPolicyTest's own
     * pinned trashed-list case — a subtask whose task's list is soft-deleted
     * must deny even for the list's own owner, rather than relying on it
     * "happening to work" through TaskPolicy's own denial.
     */
    public function test_a_subtask_whose_tasks_list_is_soft_deleted_is_denied_even_for_the_owner(): void
    {
        $policy = new SubtaskPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();
        $list->delete();

        $this->assertFalse($policy->view($owner, $subtask->fresh()));
        $this->assertFalse($policy->update($owner, $subtask->fresh()));
        $this->assertFalse($policy->delete($owner, $subtask->fresh()));
    }
}
