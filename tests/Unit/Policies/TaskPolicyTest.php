<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Task;
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
        $task = Task::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->view($owner, $task));
        $this->assertTrue($policy->update($owner, $task));
        $this->assertTrue($policy->delete($owner, $task));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = new TaskPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->view($stranger, $task));
        $this->assertFalse($policy->update($stranger, $task));
        $this->assertFalse($policy->delete($stranger, $task));
    }
}
