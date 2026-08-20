<?php

use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 8: the web mirror of the
 * `/api/v1` sharing lifecycle (`tests/Feature/Api/V1/{TaskListMemberTest,
 * TaskListMembershipTest,ListInvitationTest}.php`), plus the shared Inertia
 * props the notification center (Step 9) will read.
 */
it('invites and revokes a member through web routes', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('lists.members.store', $list), [
        'email' => $invitee->email,
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($owner)->delete(route('lists.members.destroy', [$list, $invitee]))
        ->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseMissing('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $invitee->id,
    ]);
});

it('rejects an invite from a non owner over the web route', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($member)->post(route('lists.members.store', $list), [
        'email' => $invitee->email,
    ])->assertForbidden();
});

it('lets an accepted non owner member leave and redirects to inbox', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($member)->delete(route('lists.membership.destroy', $list))
        ->assertRedirect(route('inbox'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $member->id,
    ]);
});

it('denies the owner from leaving their own list over the web route', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->delete(route('lists.membership.destroy', $list))
        ->assertForbidden();

    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $owner->id,
    ]);
});

it('accepts invitations through web routes with flash', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($invitee)->post(route('invitations.accept', $invitation))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'accepted']);

});

it('shows the newly accepted list ungrouped in the navigation tree on the next page load', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Website launch']);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($invitee)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            fn (Collection $ungroupedLists) => $ungroupedLists->doesntContain(fn (array $item) => $item['id'] === $list->id),
        ));

    $this->actingAs($invitee)->post(route('invitations.accept', $invitation))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($invitee)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            fn (Collection $ungroupedLists) => $ungroupedLists->contains(fn (array $item) => $item['id'] === $list->id),
        ));
});

it('declines an invitation through the web route', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($invitee)->post(route('invitations.decline', $invitation))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('task_list_members', ['id' => $invitation->id, 'status' => 'declined']);
});

it('rejects a stranger responding to someone elses invitation over the web route', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $stranger = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($stranger)->post(route('invitations.accept', $invitation))->assertForbidden();
    $this->actingAs($stranger)->post(route('invitations.decline', $invitation))->assertForbidden();
});

// -- Guest / non-member denial paths ----------------------------------------

it('redirects guests to login for every sharing route', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

    $this->post(route('lists.members.store', $list), ['email' => 'x@example.com'])
        ->assertRedirect(route('login'));
    $this->delete(route('lists.members.destroy', [$list, $member]))
        ->assertRedirect(route('login'));
    $this->delete(route('lists.membership.destroy', $list))
        ->assertRedirect(route('login'));
    $this->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('login'));
    $this->post(route('invitations.decline', $invitation))
        ->assertRedirect(route('login'));
});

it('denies a pending member from inviting anyone to a list they have not accepted', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $anotherInvitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($invitee)->post(route('lists.members.store', $list), [
        'email' => $anotherInvitee->email,
    ])->assertForbidden();
});

it('denies a pending member from leaving a list they have not accepted', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($invitee)->delete(route('lists.membership.destroy', $list))->assertForbidden();

    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $invitee->id,
        'status' => 'pending',
    ]);
});

it('denies a declined member from leaving a list they are no longer part of', function () {
    $owner = User::factory()->create();
    $decliner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $decliner)
        ->create(['status' => 'declined', 'responded_at' => now()]);

    $this->actingAs($decliner)->delete(route('lists.membership.destroy', $list))->assertForbidden();
});

// -- Domain-exception rendering on the web (Inertia) surface -----------------
//
// Every rejection below must take bootstrap/app.php's DomainException ->
// back()->withErrors(['domain' => ...]) branch, which only fires for a
// request carrying the X-Inertia header (see PhaseTwoInertiaTest.php's own
// ->withHeader('X-Inertia', 'true')->from(...)->assertSessionHasErrors('domain')
// idiom). Without that header a DomainException falls through as a plain
// RuntimeException — a 500, not a redirect — so these tests exist
// specifically to pin the correct code path, not just the correct rule.

it('renders the inbox-cannot-be-shared domain error inline on an inertia request', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $inbox = TaskList::factory()->inbox()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('inbox'))
        ->post(route('lists.members.store', $inbox), ['email' => $invitee->email])
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('domain');
});

it('renders the self-invite domain error inline on an inertia request', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->post(route('lists.members.store', $list), ['email' => $owner->email])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');
});

it('renders the already-member domain error inline on an inertia request', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->post(route('lists.members.store', $list), ['email' => $member->email])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');
});

it('renders the unregistered-email domain error inline on an inertia request', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->post(route('lists.members.store', $list), ['email' => 'nobody-registered@example.com'])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');
});

it('renders the member-cap domain error inline on an inertia request', function () {
    config(['sharing.max_members_per_list' => 1]);

    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->post(route('lists.members.store', $list), ['email' => $invitee->email])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');
});

it('renders the not-a-member domain error when revoking someone with no membership row', function () {
    $owner = User::factory()->create();
    $notAMember = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->delete(route('lists.members.destroy', [$list, $notAMember]))
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');
});

it('renders the owner-cannot-be-revoked domain error inline on an inertia request', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->delete(route('lists.members.destroy', [$list, $owner]))
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('domain');

    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $owner->id,
    ]);
});

it('renders the invitation-no-longer-pending domain error when accepting a declined invitation', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)
        ->create(['status' => 'declined', 'responded_at' => now()]);

    $this->actingAs($invitee)
        ->withHeader('X-Inertia', 'true')
        ->from(route('inbox'))
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('domain');
});

it('renders the invitation-no-longer-pending domain error when declining an accepted membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $membership = TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($member)
        ->withHeader('X-Inertia', 'true')
        ->from(route('inbox'))
        ->post(route('invitations.decline', $membership))
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('domain');
});

// -- Invite rate limiting -----------------------------------------------------

it('renders a tripped invite rate limit inline instead of a raw 429 for an inertia request', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($owner)
            ->post(route('lists.members.store', $list), ['email' => $invitee->email])
            ->assertRedirect();
    }

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $list))
        ->post(route('lists.members.store', $list), ['email' => $invitee->email])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasErrors('email');
});

it('still returns a plain 429 for a non inertia request once the invite limit trips', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($owner)
            ->post(route('lists.members.store', $list), ['email' => $invitee->email])
            ->assertRedirect();
    }

    $this->actingAs($owner)
        ->post(route('lists.members.store', $list), ['email' => $invitee->email])
        ->assertStatus(429);
});

// -- Shared notifications props -----------------------------------------------

it('shares the unread notification count on every request', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Website launch']);
    $this->actingAs($owner)->post(route('lists.members.store', $list), [
        'email' => $invitee->email,
    ])->assertRedirect();

    $this->actingAs($invitee)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1));
});

// -- currentList: roster, sharing flags, F18 email visibility ----------------

it('loads another accessible lists share dialog without replacing the current workspace', function () {
    $owner = User::factory()->create();
    $selectedList = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Selected list']);
    $targetList = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Target list']);
    $member = User::factory()->create();
    $invitee = User::factory()->create();
    TaskListMember::factory()->forTaskList($targetList, $member)->create();
    TaskListMember::factory()->forTaskList($targetList, $invitee)->pending()->create();

    $this->actingAs($owner)
        ->get(route('lists.show', $selectedList).'?sharing_list_id='.$targetList->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('sharingDialog', fn (Assert $page) => $page
            ->where('sharingDialog.id', $targetList->id)
            ->where('sharingDialog.name', 'Target list')
            ->where('sharingDialog.canManageSharing', true)
            ->has('sharingDialog.members', 2)
            ->has('sharingDialog.pendingInvitations', 1)));
});

it('does not expose an inaccessible list through the optional share dialog prop', function () {
    $user = User::factory()->create();
    $selectedList = TaskList::factory()->create(['user_id' => $user->id]);
    $strangersList = TaskList::factory()->create();

    $this->actingAs($user)
        ->get(route('lists.show', $selectedList).'?sharing_list_id='.$strangersList->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('sharingDialog', fn (Assert $page) => $page
            ->where('sharingDialog', null)));
});

it('renders currentList with the member roster and sharing flags for the owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($owner)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.currentList.isShared', true)
            ->where('workspace.currentList.isOwner', true)
            ->where('workspace.currentList.canManageSharing', true)
            ->has('workspace.currentList.members', 2));
});

it('shows the owner every members email in the roster', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($owner)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.currentList.members.0.email', 'owner@example.com')
            ->where('workspace.currentList.members.1.email', 'member@example.com'));
});

it('hides member emails from a non owner member on currentList', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($member)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.currentList.isOwner', false)
            ->where('workspace.currentList.canManageSharing', false)
            ->where('workspace.currentList.members.0.email', null)
            ->where('workspace.currentList.members.1.email', null));
});

it('marks an unshared list as not shared', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('workspace.currentList.isShared', false));
});

// -- currentList: pendingInvitations (Plan 1, Step 10) -----------------------

it('renders pending invitations on currentList for the owner with email visible', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $invitation = TaskListMember::factory()->forTaskList($list, $invitee)
        ->pending()
        ->create(['invited_by_user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workspace.currentList.pendingInvitations', 1)
            ->where('workspace.currentList.pendingInvitations.0.id', $invitation->id)
            ->where('workspace.currentList.pendingInvitations.0.userId', $invitee->id)
            ->where('workspace.currentList.pendingInvitations.0.email', 'invitee@example.com'));
});

it('hides pending invitations entirely from a non owner member on currentList', function () {
    // Plan 1, Step 10 code review: a pending invitee isn't a member yet and
    // may still decline — a non-owner sees an empty array, not a redacted
    // one, since an empty list would itself imply "nobody's been invited".
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();
    TaskListMember::factory()->forTaskList($list, $invitee)->pending()->create();

    $this->actingAs($member)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workspace.currentList.pendingInvitations', 0));
});

// -- Sidebar rows: isOwner / isShared (Plan 1, Step 10) -----------------------

it('marks the owner and a non owner member differently in the sidebar navigation rows', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();

    $this->actingAs($owner)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            fn (Collection $lists) => $lists->firstWhere('id', $list->id)['isOwner'] === true,
        ));

    $this->actingAs($member)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            fn (Collection $lists) => $lists->firstWhere('id', $list->id)['isOwner'] === false,
        ));
});

it('marks a shared list as shared and an unshared list as not shared in the sidebar navigation rows', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($sharedList, $member)->create();
    $unsharedList = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            function (Collection $lists) use ($sharedList, $unsharedList): bool {
                return $lists->firstWhere('id', $sharedList->id)['isShared'] === true
                    && $lists->firstWhere('id', $unsharedList->id)['isShared'] === false;
            },
        ));

    $this->actingAs($member)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'workspace.ungroupedLists',
            fn (Collection $lists) => $lists->firstWhere('id', $sharedList->id)['isShared'] === true,
        ));
});

// -- N+1 guard (N4) ------------------------------------------------------------

it('renders a shared lists workspace page in a bounded number of queries', function () {
    $owner = User::factory()->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $memberA)->create();
    TaskListMember::factory()->forTaskList($list, $memberB)->create();
    Task::factory()->forTaskList($list)->count(3)->create();

    DB::enableQueryLog();

    $this->actingAs($owner)->get(route('lists.show', $list))->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Guards against the member roster (sharingDetails()'s acceptedMembersFor())
    // or the unread notification count turning into a per-member query.
    // Measured with 2 members and 3 tasks (auth, session, unread count, the
    // workspace tree, the roster + its eager-loaded user, tasks + their
    // subtasks/comments); headroom to 20 so unrelated, legitimate query
    // additions elsewhere on this page don't make this test flaky — it
    // exists to catch an O(N) regression, not to pin an exact number.
    $this->assertLessThanOrEqual(20, $queryCount);
});
