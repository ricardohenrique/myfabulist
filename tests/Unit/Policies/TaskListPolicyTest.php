<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Policies\TaskListPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_may_view_and_update_their_list(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->view($owner, $list));
        $this->assertTrue($policy->update($owner, $list));
    }

    public function test_a_non_owner_is_denied(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->view($stranger, $list));
        $this->assertFalse($policy->update($stranger, $list));
        $this->assertFalse($policy->delete($stranger, $list));
        $this->assertFalse($policy->share($stranger, $list));
        $this->assertFalse($policy->manageMembers($stranger, $list));
    }

    public function test_the_owner_may_delete_a_normal_list(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id, 'is_default' => false]);

        $this->assertTrue($policy->delete($owner, $list));
    }

    public function test_the_default_list_is_never_deletable_even_by_its_owner(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->delete($owner, $inbox));
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4: a pending or
     * declined membership grants zero access — only 'accepted' does.
     * Constructed directly via the factory, bypassing the not-yet-built
     * invite/accept flow (Step 5).
     */
    public function test_a_pending_membership_grants_no_access(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        $this->assertFalse($policy->view($invitee, $list));
        $this->assertFalse($policy->update($invitee, $list));
        $this->assertFalse($policy->leave($invitee, $list));
    }

    public function test_a_declined_membership_grants_no_access(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $invitee)->create(['status' => 'declined']);

        $this->assertFalse($policy->view($invitee, $list));
        $this->assertFalse($policy->update($invitee, $list));
        $this->assertFalse($policy->leave($invitee, $list));
    }

    /**
     * An accepted non-owner member can view/rename the list (Q4), but
     * cannot delete it, share it, or manage its members (Q2/Q6) — only the
     * owner can. The member's only voluntary exit is leave().
     */
    public function test_an_accepted_non_owner_member_can_view_and_rename_but_not_delete_share_or_manage(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $this->assertTrue($policy->view($member, $list));
        $this->assertTrue($policy->update($member, $list));
        $this->assertTrue($policy->leave($member, $list));

        $this->assertFalse($policy->delete($member, $list));
        $this->assertFalse($policy->share($member, $list));
        $this->assertFalse($policy->manageMembers($member, $list));
    }

    /**
     * The owner has no voluntary "leave" in v1 (Q6) — their only exit is
     * delete(), which removes the list for every member.
     */
    public function test_the_owner_cannot_leave_their_own_list(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->leave($owner, $list));
    }

    public function test_a_non_member_is_denied_everywhere(): void
    {
        $policy = app(TaskListPolicy::class);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($policy->view($stranger, $list));
        $this->assertFalse($policy->update($stranger, $list));
        $this->assertFalse($policy->delete($stranger, $list));
        $this->assertFalse($policy->share($stranger, $list));
        $this->assertFalse($policy->manageMembers($stranger, $list));
        $this->assertFalse($policy->leave($stranger, $list));
    }
}
