<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Subtask;
use App\Models\Task;
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
        $task = Task::factory()->create(['user_id' => $owner->id]);
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
        $task = Task::factory()->create(['user_id' => $owner->id]);
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->assertFalse($policy->view($stranger, $subtask));
        $this->assertFalse($policy->update($stranger, $subtask));
        $this->assertFalse($policy->delete($stranger, $subtask));
    }
}
