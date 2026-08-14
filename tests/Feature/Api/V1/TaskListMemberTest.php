<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $list = TaskList::factory()->create();

        $this->getJson("/api/v1/lists/{$list->id}/members")->assertStatus(401);
        $this->postJson("/api/v1/lists/{$list->id}/members", ['email' => 'x@example.com'])->assertStatus(401);
        $this->deleteJson("/api/v1/lists/{$list->id}/members/1")->assertStatus(401);
    }

    // -- GET lists/{list}/members ------------------------------------------------

    public function test_the_owner_sees_the_full_roster_with_every_members_email(): void
    {
        $owner = User::factory()->create();
        $memberOne = User::factory()->create();
        $memberTwo = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $memberOne)->create();
        TaskListMember::factory()->forTaskList($list, $memberTwo)->create();

        $response = $this->actingAs($owner)->getJson("/api/v1/lists/{$list->id}/members");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $emails = collect($response->json('data'))->pluck('user.email')->all();
        $this->assertContains($owner->email, $emails);
        $this->assertContains($memberOne->email, $emails);
        $this->assertContains($memberTwo->email, $emails);
    }

    public function test_an_accepted_non_owner_member_sees_the_roster_with_every_email_hidden(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();
        TaskListMember::factory()->forTaskList($list, $stranger)->pending()->create();

        $response = $this->actingAs($member)->getJson("/api/v1/lists/{$list->id}/members");

        $response->assertOk();
        // Pending rows never appear on the accepted roster.
        $response->assertJsonCount(2, 'data');
        $emails = collect($response->json('data'))->pluck('user.email')->all();
        $this->assertSame([null, null], $emails);

        $ownerRow = collect($response->json('data'))->firstWhere('user.id', $owner->id);
        $this->assertTrue($ownerRow['is_owner']);
        $memberRow = collect($response->json('data'))->firstWhere('user.id', $member->id);
        $this->assertFalse($memberRow['is_owner']);
    }

    public function test_a_pending_member_cannot_view_the_roster(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $this->actingAs($invitee)->getJson("/api/v1/lists/{$list->id}/members")->assertForbidden();
    }

    public function test_a_declined_member_cannot_view_the_roster(): void
    {
        $owner = User::factory()->create();
        $decliner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $decliner)
            ->create(['status' => 'declined', 'responded_at' => now()]);

        $this->actingAs($decliner)->getJson("/api/v1/lists/{$list->id}/members")->assertForbidden();
    }

    public function test_a_stranger_cannot_view_the_roster(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->getJson("/api/v1/lists/{$list->id}/members")->assertForbidden();
    }

    // -- POST lists/{list}/members -------------------------------------------

    public function test_the_owner_can_invite_a_registered_user_by_email(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.user.id', $invitee->id);
        $response->assertJsonPath('data.user.email', $invitee->email);
        $response->assertJsonPath('data.is_owner', false);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $invitee->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_non_owner_member_cannot_invite(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $response = $this->actingAs($member)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertForbidden();
    }

    public function test_inviting_to_the_inbox_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$inbox->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'task_list_cannot_be_shared');
    }

    public function test_inviting_yourself_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => $owner->email,
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'cannot_invite_self');
    }

    public function test_inviting_an_already_accepted_member_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => $member->email,
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'already_member');
    }

    public function test_inviting_an_unregistered_email_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => 'nobody-registered@example.com',
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'user_not_found_for_invitation');
    }

    public function test_inviting_past_the_member_cap_is_rejected_with_its_error_code(): void
    {
        config(['sharing.max_members_per_list' => 2]);

        $owner = User::factory()->create();
        $existingMember = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $existingMember)->create();

        $response = $this->actingAs($owner)->postJson("/api/v1/lists/{$list->id}/members", [
            'email' => $invitee->email,
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'task_list_member_limit_reached');
    }

    public function test_invite_creation_is_rate_limited(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($owner)
                ->postJson("/api/v1/lists/{$list->id}/members", ['email' => $invitee->email])
                ->assertCreated();
        }

        $this->actingAs($owner)
            ->postJson("/api/v1/lists/{$list->id}/members", ['email' => $invitee->email])
            ->assertStatus(429);
    }

    // -- DELETE lists/{list}/members/{user} ----------------------------------

    public function test_the_owner_can_revoke_a_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $response = $this->actingAs($owner)->deleteJson("/api/v1/lists/{$list->id}/members/{$member->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_the_owner_cannot_revoke_themselves(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/lists/{$list->id}/members/{$owner->id}");

        $response->assertStatus(422)->assertJsonPath('error_code', 'owner_membership_cannot_be_removed');
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_revoking_a_user_with_no_membership_row_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $notAMember = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/lists/{$list->id}/members/{$notAMember->id}");

        $response->assertStatus(422)->assertJsonPath('error_code', 'not_a_member');
    }

    public function test_a_non_owner_member_cannot_revoke_anyone(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $memberA)->create();
        TaskListMember::factory()->forTaskList($list, $memberB)->create();

        $response = $this->actingAs($memberA)->deleteJson("/api/v1/lists/{$list->id}/members/{$memberB->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $memberB->id,
        ]);
    }
}
