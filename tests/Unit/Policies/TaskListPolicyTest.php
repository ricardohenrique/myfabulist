<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\TaskList;
use App\Models\User;
use App\Policies\TaskListPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_may_view_and_update_their_list(): void
    {
        $policy = new TaskListPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->view($owner, $list));
        $this->assertTrue($policy->update($owner, $list));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = new TaskListPolicy;
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->view($stranger, $list));
        $this->assertFalse($policy->update($stranger, $list));
        $this->assertFalse($policy->delete($stranger, $list));
    }

    public function test_the_owner_may_delete_a_normal_list(): void
    {
        $policy = new TaskListPolicy;
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id, 'is_default' => false]);

        $this->assertTrue($policy->delete($owner, $list));
    }

    public function test_the_default_list_is_never_deletable_even_by_its_owner(): void
    {
        $policy = new TaskListPolicy;
        $owner = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->delete($owner, $inbox));
    }
}
