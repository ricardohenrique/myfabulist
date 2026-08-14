<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/invitations')->assertStatus(401);
        $this->postJson('/api/v1/invitations/1/accept')->assertStatus(401);
        $this->postJson('/api/v1/invitations/1/decline')->assertStatus(401);
    }

    // -- GET invitations -------------------------------------------------------

    public function test_a_user_sees_only_their_own_pending_invitations(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $someoneElse = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $inviter->id, 'name' => 'Website launch']);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)
            ->pending()
            ->create(['invited_by_user_id' => $inviter->id]);
        // Someone else's pending invitation must never leak into this user's list.
        TaskListMember::factory()->forTaskList(
            TaskList::factory()->create(['user_id' => $inviter->id]),
            $someoneElse,
        )->pending()->create();

        $response = $this->actingAs($invitee)->getJson('/api/v1/invitations');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $invitation->id);
        $response->assertJsonPath('data.0.list.id', $list->id);
        $response->assertJsonPath('data.0.list.name', 'Website launch');
        $response->assertJsonPath('data.0.invited_by.id', $inviter->id);
    }

    public function test_an_accepted_or_declined_membership_never_appears_as_a_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $accepted = User::factory()->create();
        $declined = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $accepted)->create();
        TaskListMember::factory()->forTaskList($list, $declined)
            ->create(['status' => 'declined', 'responded_at' => now()]);

        $this->actingAs($accepted)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($declined)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data');
    }

    // -- POST invitations/{id}/accept ------------------------------------------

    public function test_the_invitee_can_accept_their_own_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $response = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/accept");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'accepted');
        $response->assertJsonPath('data.user.id', $invitee->id);
        $this->assertDatabaseHas('task_list_members', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
    }

    public function test_a_stranger_cannot_accept_someone_elses_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $response = $this->actingAs($stranger)->postJson("/api/v1/invitations/{$invitation->id}/accept");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'pending']);
    }

    public function test_accepting_a_previously_declined_invitation_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)
            ->create(['status' => 'declined', 'responded_at' => now()]);

        $response = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/accept");

        $response->assertStatus(422)->assertJsonPath('error_code', 'invitation_no_longer_pending');
    }

    // -- POST invitations/{id}/decline -----------------------------------------

    public function test_the_invitee_can_decline_their_own_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $response = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/decline");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'declined');
        $this->assertDatabaseHas('task_list_members', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
    }

    public function test_a_stranger_cannot_decline_someone_elses_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $response = $this->actingAs($stranger)->postJson("/api/v1/invitations/{$invitation->id}/decline");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'pending']);
    }

    public function test_declining_an_already_accepted_membership_is_rejected_with_its_error_code(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $membership = TaskListMember::factory()->forTaskList($list, $member)->create();

        $response = $this->actingAs($member)->postJson("/api/v1/invitations/{$membership->id}/decline");

        $response->assertStatus(422)->assertJsonPath('error_code', 'invitation_no_longer_pending');
    }

    // -- Regression: invitation to a since-deleted list ------------------------

    /**
     * Code-review follow-up: the owner can soft-delete a shared list while
     * an invitee still has a `pending` row for it — the invitee's still-live
     * "Accept"/"Decline" button (Step 9's notification panel hands out ids
     * that can legitimately go stale) must never reach a null `taskList` and
     * 500. Route-model-binding for `{invitation}` excludes rows whose list
     * is gone, so this must resolve as a clean 404 instead.
     */
    public function test_accepting_an_invitation_to_a_since_deleted_list_returns_a_clean_404_not_a_500(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();
        $list->delete();

        $response = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/accept");

        $response->assertNotFound();
        $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'pending']);
    }

    public function test_declining_an_invitation_to_a_since_deleted_list_returns_a_clean_404_not_a_500(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();
        $list->delete();

        $response = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->id}/decline");

        $response->assertNotFound();
        $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'pending']);
    }
}
