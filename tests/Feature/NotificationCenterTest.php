<?php

use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Services\TaskCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('keeps an invitation notification after acceptance and disables both response actions', function () {
    $owner = User::factory()->create(['name' => 'Ada Owner']);
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Launch plan']);

    $this->actingAs($owner)->post(route('lists.members.store', $list), [
        'email' => $invitee->email,
    ])->assertRedirect();

    $notification = $invitee->notifications()->sole();
    $membership = TaskListMember::query()
        ->where('task_list_id', $list->id)
        ->where('user_id', $invitee->id)
        ->sole();

    $this->actingAs($invitee)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/show')
            ->where('workspace.view', 'notifications')
            ->where('workspace.notificationItems.0.id', $notification->id)
            ->where('workspace.notificationItems.0.type', 'list_invitation')
            ->where('workspace.notificationItems.0.invitation.status', 'pending')
            ->where('workspace.notificationItems.0.invitation.canRespond', true)
            ->where('notifications.unreadCount', 1));

    $this->actingAs($invitee)->post(route('invitations.accept', $membership))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseCount('notifications', 1);
    $this->actingAs($invitee)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.notificationItems.0.id', $notification->id)
            ->where('workspace.notificationItems.0.invitation.status', 'accepted')
            ->where('workspace.notificationItems.0.invitation.canRespond', false)
            ->where('workspace.notificationItems.0.readAt', fn ($value) => is_string($value))
            ->where('workspace.notificationItems.0.targetUrl', route('lists.show', $list, false))
            ->where('notifications.unreadCount', 0));
});

it('keeps a declined attempt when the same person is invited again', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('lists.members.store', $list), ['email' => $invitee->email]);
    $membership = TaskListMember::query()
        ->where('task_list_id', $list->id)
        ->where('user_id', $invitee->id)
        ->sole();
    $firstNotificationId = $invitee->notifications()->sole()->id;

    $this->actingAs($invitee)->post(route('invitations.decline', $membership))->assertRedirect();
    $this->travel(1)->seconds();
    $this->actingAs($owner)->post(route('lists.members.store', $list), ['email' => $invitee->email])->assertRedirect();

    $this->assertDatabaseCount('notifications', 2);
    $this->actingAs($invitee)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('workspace.notificationItems', 2)
            ->where('workspace.notificationItems.0.invitation.status', 'pending')
            ->where('workspace.notificationItems.0.invitation.canRespond', true)
            ->where('workspace.notificationItems.1.id', $firstNotificationId)
            ->where('workspace.notificationItems.1.invitation.status', 'declined')
            ->where('workspace.notificationItems.1.invitation.canRespond', false));
});

it('fans a shared task comment out to every other accepted member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    $pendingUser = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Shared launch']);
    TaskListMember::factory()->forTaskList($list, $member)->create();
    TaskListMember::factory()->forTaskList($list, $otherMember)->create();
    TaskListMember::factory()->forTaskList($list, $pendingUser)->pending()->create();
    $task = Task::factory()->forTaskList($list)->create(['title' => 'Write release notes']);

    app(TaskCommentService::class)->create($task, $member, 'Ready for review.');

    expect($member->notifications()->count())->toBe(0)
        ->and($pendingUser->notifications()->count())->toBe(0)
        ->and($owner->notifications()->count())->toBe(1)
        ->and($otherMember->notifications()->count())->toBe(1);

    $this->actingAs($owner)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.notificationItems.0.type', 'task_comment')
            ->where('workspace.notificationItems.0.actor.id', $member->id)
            ->where('workspace.notificationItems.0.task.id', $task->id)
            ->where('workspace.notificationItems.0.body', 'Ready for review.')
            ->where('workspace.notificationItems.0.targetUrl', route('lists.show', ['list' => $list, 'task' => $task->id], false)));
});

it('does not create a comment notification when a list has no other accepted member', function () {
    $owner = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $task = Task::factory()->forTaskList($list)->create();

    app(TaskCommentService::class)->create($task, $owner, 'A private note.');

    $this->assertDatabaseCount('notifications', 0);
});

it('marks a notification read on open and redirects to the referenced task', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();
    $task = Task::factory()->forTaskList($list)->create();
    app(TaskCommentService::class)->create($task, $member, 'Please check this.');
    $notification = $owner->notifications()->sole();

    $this->actingAs($owner)->post(route('notifications.open', $notification->id))
        ->assertRedirect(route('lists.show', ['list' => $list, 'task' => $task->id], false));

    expect($notification->refresh()->read_at)->not->toBeNull();
    $this->assertDatabaseCount('notifications', 1);
});

it('lets a user toggle read state without removing the notification', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->post(route('lists.members.store', $list), ['email' => $invitee->email]);
    $notification = $invitee->notifications()->sole();

    $this->actingAs($invitee)->patch(route('notifications.update', $notification->id), ['read' => true])
        ->assertRedirect();
    expect($notification->refresh()->read_at)->not->toBeNull();

    $this->actingAs($invitee)->patch(route('notifications.update', $notification->id), ['read' => false])
        ->assertRedirect();
    expect($notification->refresh()->read_at)->toBeNull();
    $this->assertDatabaseCount('notifications', 1);
});

it('does not let another user open or mutate a notification', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $stranger = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->post(route('lists.members.store', $list), ['email' => $invitee->email]);
    $notification = $invitee->notifications()->sole();

    $this->actingAs($stranger)->post(route('notifications.open', $notification->id))->assertNotFound();
    $this->actingAs($stranger)->patch(route('notifications.update', $notification->id), ['read' => true])->assertNotFound();
    expect($notification->refresh()->read_at)->toBeNull();
});

it('exposes persistent notifications and read state through the versioned api', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->post(route('lists.members.store', $list), ['email' => $invitee->email]);
    $notification = $invitee->notifications()->sole();

    $this->actingAs($invitee)->getJson(route('api.v1.notifications.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $notification->id)
        ->assertJsonPath('data.0.type', 'list_invitation')
        ->assertJsonPath('data.0.invitation.status', 'pending')
        ->assertJsonPath('data.0.invitation.can_respond', true);

    $this->actingAs($invitee)->patchJson(route('api.v1.notifications.update', $notification->id), ['read' => true])
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.read_at', fn ($value) => is_string($value));

    $this->actingAs($invitee)->getJson(route('api.v1.notifications.index', ['filter' => 'unread']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->assertDatabaseCount('notifications', 1);
});
