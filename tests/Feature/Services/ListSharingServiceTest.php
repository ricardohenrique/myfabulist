<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\AlreadyMemberException;
use App\Exceptions\CannotInviteSelfException;
use App\Exceptions\InvitationNoLongerPendingException;
use App\Exceptions\NotAMemberException;
use App\Exceptions\NotListOwnerException;
use App\Exceptions\OwnerMembershipCannotBeRemovedException;
use App\Exceptions\TaskListCannotBeSharedException;
use App\Exceptions\TaskListMemberLimitReachedException;
use App\Exceptions\UserNotFoundForInvitationException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Services\ListSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSharingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBER_CAP = 3;

    private ListSharingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ListSharingService::class);

        // Set explicitly, not left at the config default — these tests
        // exercise the cap *mechanism*, not today's default value, and
        // must keep working unchanged if that default is ever tuned.
        config(['sharing.max_members_per_list' => self::MEMBER_CAP]);
    }

    // -- invite() -------------------------------------------------------

    public function test_invite_creates_a_pending_membership_row(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $membership = $this->service->invite($list, $owner, 'invitee@example.com');

        $this->assertSame('pending', $membership->status);
        $this->assertSame($invitee->id, $membership->user_id);
        $this->assertSame($owner->id, $membership->invited_by_user_id);
        $this->assertNotNull($membership->invited_at);
        $this->assertNull($membership->responded_at);
    }

    /**
     * Q3: "resolves by exact lowercase email match" — proves this actually
     * holds end-to-end (trimmed, differently-cased input still resolves a
     * user stored lowercase), not just that the Service happens to call
     * Str::lower() somewhere.
     */
    public function test_invite_resolves_the_invitee_despite_surrounding_whitespace_and_case(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $membership = $this->service->invite($list, $owner, '  Invitee@Example.com ');

        $this->assertSame($invitee->id, $membership->user_id);
    }

    public function test_invite_rejects_the_default_inbox_list(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->inbox()->create(['user_id' => $owner->id]);
        User::factory()->create(['email' => 'invitee@example.com']);

        $this->expectException(TaskListCannotBeSharedException::class);

        $this->service->invite($list, $owner, 'invitee@example.com');
    }

    public function test_invite_rejects_an_unregistered_email(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->expectException(UserNotFoundForInvitationException::class);

        $this->service->invite($list, $owner, 'nobody@example.com');
    }

    public function test_invite_rejects_inviting_self(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->expectException(CannotInviteSelfException::class);

        $this->service->invite($list, $owner, 'owner@example.com');
    }

    public function test_invite_rejects_a_user_who_is_already_an_accepted_member(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create(['email' => 'member@example.com']);
        TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'accepted']);

        $this->expectException(AlreadyMemberException::class);

        $this->service->invite($list, $owner, 'member@example.com');
    }

    public function test_invite_succeeds_at_exactly_one_below_the_cap(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        // Owner (1) + 1 more = cap - 1 = 2 accepted, one slot still free.
        $this->fillWithAcceptedMembers($list, self::MEMBER_CAP - 2);
        $lastSlot = User::factory()->create(['email' => 'last-slot@example.com']);

        $membership = $this->service->invite($list, $owner, 'last-slot@example.com');

        $this->assertSame('pending', $membership->status);
        $this->assertSame($lastSlot->id, $membership->user_id);
    }

    public function test_invite_rejects_when_the_list_is_already_at_the_member_cap(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        // Owner's own membership counts as 1 — fill the remaining slots.
        $this->fillWithAcceptedMembers($list, self::MEMBER_CAP - 1);
        $rejected = User::factory()->create(['email' => 'rejected@example.com']);

        try {
            $this->service->invite($list, $owner, 'rejected@example.com');
            $this->fail('Expected TaskListMemberLimitReachedException to be thrown.');
        } catch (TaskListMemberLimitReachedException) {
            // expected
        }

        $this->assertDatabaseMissing('task_list_members', ['task_list_id' => $list->id, 'user_id' => $rejected->id]);
    }

    public function test_invite_is_idempotent_for_a_duplicate_pending_invite_even_at_the_cap(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $pending = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

        // Fill the list to the cap with *other* accepted members — the
        // pending invite above must remain untouched by the cap check.
        $this->fillWithAcceptedMembers($list, self::MEMBER_CAP - 1);

        $result = $this->service->invite($list, $owner, 'invitee@example.com');

        $this->assertSame($pending->id, $result->id);
        $this->assertSame(1, TaskListMember::query()->where('task_list_id', $list->id)->where('user_id', $invitee->id)->count());
    }

    public function test_invite_reactivates_the_same_row_after_a_decline(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $declined = TaskListMember::factory()->forTaskList($list, $invitee)->create([
            'status' => 'declined',
            'invited_at' => now()->subDay(),
            'responded_at' => now()->subHour(),
        ]);

        $result = $this->service->invite($list, $owner, 'invitee@example.com');

        $this->assertSame($declined->id, $result->id);
        $this->assertSame('pending', $result->status);
        $this->assertNull($result->responded_at);
        $this->assertTrue($result->invited_at->greaterThan($declined->invited_at));
    }

    // -- accept() ---------------------------------------------------------

    public function test_accept_sets_status_response_and_places_the_list_ungrouped_at_the_end(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();
        // The accepting user already has an unrelated accepted list at
        // position 0 ungrouped, so the next slot must be 1.
        TaskListMember::factory()->create(['user_id' => $member->id, 'folder_id' => null, 'position' => 0, 'status' => 'accepted']);
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $accepted = $this->service->accept($pending, $member);

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->responded_at);
        $this->assertNull($accepted->folder_id);
        $this->assertSame(1, $accepted->position);
    }

    public function test_accept_does_not_affect_the_sharers_own_placement_of_the_same_list(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->inFolder(Folder::factory()->for($owner)->create(), 4)->create(['user_id' => $owner->id]);
        $ownerMembership = TaskListMember::query()->where('task_list_id', $list->id)->where('user_id', $owner->id)->sole();
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $this->service->accept($pending, $member);

        $this->assertSame($ownerMembership->folder_id, $ownerMembership->fresh()->folder_id);
        $this->assertSame($ownerMembership->position, $ownerMembership->fresh()->position);
    }

    public function test_accept_succeeds_at_exactly_one_below_the_cap(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        // Owner (1) + 1 more = cap - 1 = 2 accepted, one slot still free.
        $this->fillWithAcceptedMembers($list, self::MEMBER_CAP - 2);
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $accepted = $this->service->accept($pending, $member);

        $this->assertSame('accepted', $accepted->status);
    }

    public function test_accept_rejects_when_the_caller_is_not_the_membership_owner(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $this->expectException(NotAMemberException::class);

        $this->service->accept($pending, $stranger);
    }

    public function test_accept_is_idempotent_for_an_already_accepted_membership(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $accepted = TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'accepted', 'folder_id' => null, 'position' => 2]);

        $result = $this->service->accept($accepted, $member);

        $this->assertTrue($result->is($accepted));
        $this->assertSame(2, $result->position);
    }

    public function test_accept_rejects_a_declined_membership(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $declined = TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'declined']);

        $this->expectException(InvitationNoLongerPendingException::class);

        $this->service->accept($declined, $member);
    }

    public function test_accept_rejects_when_the_cap_was_reached_by_other_accepts_since_the_invite_was_sent(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        // Owner (1) + the rest = the cap, all accepted *after* $pending was
        // created.
        $this->fillWithAcceptedMembers($list, self::MEMBER_CAP - 1);

        try {
            $this->service->accept($pending, $member);
            $this->fail('Expected TaskListMemberLimitReachedException to be thrown.');
        } catch (TaskListMemberLimitReachedException) {
            // expected
        }

        $this->assertSame('pending', $pending->fresh()->status);
    }

    // -- decline() --------------------------------------------------------

    public function test_decline_sets_status_and_responded_at(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $declined = $this->service->decline($pending, $member);

        $this->assertSame('declined', $declined->status);
        $this->assertNotNull($declined->responded_at);
    }

    public function test_decline_twice_is_idempotent(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $first = $this->service->decline($pending, $member);
        $second = $this->service->decline($pending->fresh(), $member);

        $this->assertSame('declined', $first->status);
        $this->assertSame('declined', $second->status);
        $this->assertTrue($second->responded_at->greaterThanOrEqualTo($first->responded_at));
    }

    public function test_decline_rejects_when_the_caller_is_not_the_membership_owner(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $this->expectException(NotAMemberException::class);

        $this->service->decline($pending, $stranger);
    }

    /**
     * Regression test for the bug the code review caught: without a status
     * guard, decline() would happily flip the *owner's own* (always
     * `accepted`) membership row to `declined`. Once that happens the list
     * is permanently orphaned — TaskListPolicy::view() denies the owner
     * access to their own list, share()/manageMembers() are owner-only so
     * nobody else can re-invite them, and invite() on themselves throws
     * CannotInviteSelfException. There is no recovery path, so this must be
     * rejected outright rather than silently succeeding.
     */
    public function test_decline_rejects_an_accepted_membership(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $ownersMembership = TaskListMember::query()->where('task_list_id', $list->id)->where('user_id', $owner->id)->sole();

        try {
            $this->service->decline($ownersMembership, $owner);
            $this->fail('Expected InvitationNoLongerPendingException to be thrown.');
        } catch (InvitationNoLongerPendingException) {
            // expected
        }

        $this->assertSame('accepted', $ownersMembership->fresh()->status);
    }

    /**
     * The same guard applies to any accepted member, not just the owner —
     * an accepted membership is simply not a "pending invitation" any more.
     */
    public function test_decline_rejects_an_accepted_membership_for_a_non_owner_member(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $accepted = TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'accepted']);

        $this->expectException(InvitationNoLongerPendingException::class);

        $this->service->decline($accepted, $member);
    }

    // -- revoke() -----------------------------------------------------------

    public function test_revoke_removes_the_members_row(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();
        $membership = TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'accepted']);

        $this->service->revoke($list, $owner, $member);

        $this->assertDatabaseMissing('task_list_members', ['id' => $membership->id]);
    }

    public function test_revoke_rejects_when_the_actor_is_not_the_owner(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $anotherMember = User::factory()->create();
        TaskListMember::factory()->forTaskList($list, $anotherMember)->create(['status' => 'accepted']);
        $target = User::factory()->create();
        $membership = TaskListMember::factory()->forTaskList($list, $target)->create(['status' => 'accepted']);

        try {
            $this->service->revoke($list, $anotherMember, $target);
            $this->fail('Expected NotListOwnerException to be thrown.');
        } catch (NotListOwnerException) {
            // expected
        }

        $this->assertDatabaseHas('task_list_members', ['id' => $membership->id]);
    }

    public function test_revoke_rejects_targeting_the_owner(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->expectException(OwnerMembershipCannotBeRemovedException::class);

        $this->service->revoke($list, $owner, $owner);
    }

    public function test_revoke_rejects_a_target_with_no_membership_row(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $stranger = User::factory()->create();

        $this->expectException(NotAMemberException::class);

        $this->service->revoke($list, $owner, $stranger);
    }

    // -- leave() ------------------------------------------------------------

    public function test_leave_removes_the_callers_row(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $membership = TaskListMember::factory()->forTaskList($list, $member)->create(['status' => 'accepted']);

        $this->service->leave($list, $member);

        $this->assertDatabaseMissing('task_list_members', ['id' => $membership->id]);
    }

    public function test_owner_cannot_leave(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->expectException(OwnerMembershipCannotBeRemovedException::class);

        $this->service->leave($list, $owner);
    }

    public function test_leave_rejects_a_caller_with_no_membership_row(): void
    {
        $list = TaskList::factory()->create();
        $stranger = User::factory()->create();

        $this->expectException(NotAMemberException::class);

        $this->service->leave($list, $stranger);
    }

    /**
     * Add $count freshly-created users as accepted members of $list.
     */
    private function fillWithAcceptedMembers(TaskList $list, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            TaskListMember::factory()->forTaskList($list, User::factory()->create())->create(['status' => 'accepted']);
        }
    }
}
