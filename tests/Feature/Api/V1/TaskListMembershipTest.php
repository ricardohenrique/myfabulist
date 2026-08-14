<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $list = TaskList::factory()->create();

        $this->deleteJson("/api/v1/lists/{$list->id}/membership")->assertStatus(401);
    }

    public function test_an_accepted_non_owner_member_can_leave(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $response = $this->actingAs($member)->deleteJson("/api/v1/lists/{$list->id}/membership");

        $response->assertNoContent();
        $this->assertDatabaseMissing('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $member->id,
        ]);
    }

    /**
     * TaskListPolicy::leave() already excludes the owner (Q6: no
     * ownership-transfer mechanism in v1), so this must be denied at the
     * Policy layer (403), not surface ListSharingService::leave()'s own
     * 422 OwnerMembershipCannotBeRemovedException.
     */
    public function test_the_owner_cannot_leave_and_is_denied_at_the_policy_layer(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/lists/{$list->id}/membership");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_a_stranger_cannot_leave_a_list_they_are_not_a_member_of(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->deleteJson("/api/v1/lists/{$list->id}/membership");

        $response->assertForbidden();
    }

    public function test_a_pending_member_cannot_leave(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $response = $this->actingAs($invitee)->deleteJson("/api/v1/lists/{$list->id}/membership");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $invitee->id,
            'status' => 'pending',
        ]);
    }
}
