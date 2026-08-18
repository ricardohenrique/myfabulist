<?php

use App\Models\Folder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A list's folder_id/position are per-viewer placement (Plan 1, Step 2) —
 * they live on the acting user's own task_list_members row, not on
 * task_lists itself, so assertions against a list's placement read this
 * table directly instead of `$list->fresh()->position`.
 */
function memberPlacement(TaskList $list, User $user): TaskListMember
{
    return TaskListMember::query()
        ->where('task_list_id', $list->id)
        ->where('user_id', $user->id)
        ->sole();
}

it('protects every workspace page with session authentication', function () {
    $list = TaskList::factory()->create();

    $this->get(route('inbox'))->assertRedirect(route('login'));
    $this->get(route('starred'))->assertRedirect(route('login'));
    $this->get(route('lists.show', $list))->assertRedirect(route('login'));
});

it('renders an unverified users inbox from canonical services', function () {
    $user = User::factory()->unverified()->create(['name' => 'Ada Lovelace']);
    $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
    $active = Task::factory()->forTaskList($inbox)->create([
        'title' => 'Active first',
        'position' => 0,
    ]);
    $subtask = Subtask::factory()->forTask($active)->create(['title' => 'First step']);
    $completed = Task::factory()->forTaskList($inbox)->completed()->create([
        'title' => 'Completed later',
    ]);

    $this->actingAs($user)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/show')
            ->where('auth.user.name', 'Ada Lovelace')
            ->where('workspace.view', 'inbox')
            ->where('workspace.currentList.id', $inbox->id)
            ->where('workspace.tasks.0.id', $active->id)
            ->where('workspace.tasks.0.createdAt', $active->created_at?->toIso8601String())
            ->where('workspace.tasks.0.subtasks.0.id', $subtask->id)
            ->where('workspace.tasks.0.subtasks.0.title', 'First step')
            ->where('workspace.tasks.0.subtasks.0.isCompleted', false)
            ->where('workspace.tasks.1.id', $completed->id)
            ->where('workspace.completedCount', 1)
            ->where('workspace.inbox.activeTaskCount', 1));
});

it('creates renames completes restores and deletes subtasks through web adapters', function () {
    $user = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $user->id]);
    $task = Task::factory()->forTaskList($list)->create();

    $this->actingAs($user)->post(route('tasks.subtasks.store', $task), [
        'title' => '  First step  ',
    ])->assertSessionHasNoErrors();

    $subtask = Subtask::query()->where('task_id', $task->id)->firstOrFail();
    $this->assertSame('First step', $subtask->title);

    $this->actingAs($user)->put(route('subtasks.update', $subtask), [
        'title' => 'Renamed step',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'title' => 'Renamed step']);

    $this->actingAs($user)->post(route('subtasks.complete', $subtask))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'is_completed' => true]);

    $this->actingAs($user)->post(route('subtasks.restore', $subtask))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'is_completed' => false]);

    $this->actingAs($user)->delete(route('subtasks.destroy', $subtask))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
});

it('renders task comments and creates them through the web adapter', function () {
    $user = User::factory()->create(['name' => 'Grace Hopper']);
    $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
    $task = Task::factory()->forTaskList($inbox)->create();
    $existing = TaskComment::factory()->forTask($task, $user)->create(['body' => 'Existing context']);

    $this->actingAs($user)->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.tasks.0.comments.0.id', $existing->id)
            ->where('workspace.tasks.0.comments.0.body', 'Existing context')
            ->where('workspace.tasks.0.comments.0.author.name', 'Grace Hopper'));

    $this->actingAs($user)->post(route('tasks.comments.store', $task), [
        'body' => '  New context  ',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('task_comments', [
        'task_id' => $task->id,
        'user_id' => $user->id,
        'body' => 'New context',
    ]);
});

it('renders ordered folder navigation and the selected list', function () {
    $user = User::factory()->create();
    $secondFolder = Folder::factory()->for($user)->create(['name' => 'Second', 'position' => 1]);
    $firstFolder = Folder::factory()->for($user)->create(['name' => 'First', 'position' => 0]);
    $list = TaskList::factory()->inFolder($firstFolder, 0)->create(['name' => 'Launch']);
    Task::factory()->forTaskList($list)->create(['title' => 'Ship it']);

    $this->actingAs($user)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/show')
            ->where('workspace.view', 'list')
            ->where('workspace.heading', 'Launch')
            ->where('workspace.eyebrow', 'First')
            ->where('workspace.folders.0.id', $firstFolder->id)
            ->where('workspace.folders.1.id', $secondFolder->id)
            ->where('workspace.tasks.0.title', 'Ship it'));
});

it('renders starred tasks with their source-list context', function () {
    $user = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Travel']);
    $task = Task::factory()->forTaskList($list)->starred()->create(['title' => 'Renew passport']);
    Task::factory()->forTaskList($list)->create(['title' => 'Not important']);

    $this->actingAs($user)->get(route('starred'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/show')
            ->where('workspace.view', 'starred')
            ->where('workspace.canAddTask', false)
            ->where('workspace.tasks.0.id', $task->id)
            ->where('workspace.tasks.0.taskListName', 'Travel')
            ->has('workspace.tasks', 1));
});

it('does not expose another users list', function () {
    $user = User::factory()->create();
    $foreignList = TaskList::factory()->create();

    $this->actingAs($user)->get(route('lists.show', $foreignList))->assertForbidden();
});

it('creates folders lists and tasks through web service adapters', function () {
    $user = User::factory()->create();
    $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('folders.store'), ['name' => '  Work  '])
        ->assertSessionHasNoErrors();
    $folder = Folder::query()->where('user_id', $user->id)->where('name', 'Work')->firstOrFail();

    $this->actingAs($user)->post(route('lists.store'), [
        'name' => 'Launch',
        'folder_id' => $folder->id,
    ])->assertSessionHasNoErrors();
    $list = TaskList::query()->where('user_id', $user->id)->where('name', 'Launch')->firstOrFail();

    $this->actingAs($user)->post(route('lists.tasks.store', $list), ['title' => '  Ship it  '])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Work']);
    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);
    $this->assertDatabaseHas('tasks', ['task_list_id' => $list->id, 'title' => 'Ship it']);
    $this->assertDatabaseHas('task_lists', ['id' => $inbox->id, 'is_default' => true]);
});

it('updates moves stars completes restores and deletes tasks', function () {
    $user = User::factory()->create();
    $source = TaskList::factory()->create(['user_id' => $user->id]);
    $target = TaskList::factory()->create(['user_id' => $user->id]);
    $task = Task::factory()->forTaskList($source)->create(['title' => 'Original']);

    $this->actingAs($user)->put(route('tasks.update', $task), [
        'title' => 'Updated',
        'note' => 'Notes',
        'due_date' => '2026-08-20',
        'is_starred' => true,
        'task_list_id' => $target->id,
    ])->assertSessionHasNoErrors();

    expect($task->fresh())
        ->title->toBe('Updated')
        ->note->toBe('Notes')
        ->task_list_id->toBe($target->id);
    $this->assertDatabaseHas('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('tasks.complete', $task))->assertSessionHasNoErrors();
    expect($task->fresh()->is_completed)->toBeTrue();

    $this->actingAs($user)->post(route('tasks.restore', $task))->assertSessionHasNoErrors();
    expect($task->fresh()->is_completed)->toBeFalse();

    $this->actingAs($user)->put(route('tasks.star', $task), ['is_starred' => false])->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('tasks.move', $task), ['task_list_id' => $source->id])->assertSessionHasNoErrors();
    expect($task->fresh()->task_list_id)->toBe($source->id);

    $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertSessionHasNoErrors();
    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 4 code-review follow-up:
 * `resources/js/layouts/app-shell.tsx` submits the task's current
 * `task_list_id` on every save, not only on an actual move. For a task in a
 * shared list that id belongs to the *owner*, not the acting (accepted,
 * non-owner) member — `Web\UpdateTaskRequest` must not 422 an ordinary
 * title/note/due-date edit just because the round-tripped list id isn't one
 * the member owns. The membership here is constructed directly via the
 * factory, ahead of the real invite/accept flow (Step 5).
 */
it('lets an accepted non owner member edit a task in a shared list over the web route', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $owner->id]);
    TaskListMember::factory()->forTaskList($list, $member)->create();
    $task = Task::factory()->forTaskList($list)->create(['title' => 'Original']);

    $this->actingAs($member)->put(route('tasks.update', $task), [
        'title' => 'Edited by a collaborator',
        'note' => null,
        'due_date' => null,
        'is_starred' => false,
        'task_list_id' => $task->task_list_id,
    ])->assertSessionHasNoErrors();

    expect($task->fresh()->title)->toBe('Edited by a collaborator');
});

it('renames moves and soft deletes lists while protecting the inbox', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->for($user)->create();
    $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Old']);
    $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('lists.update', $list), [
        'name' => 'New',
        'folder_id' => $folder->id,
    ])->assertSessionHasNoErrors();

    expect($list->fresh()->name)->toBe('New');
    expect(memberPlacement($list, $user)->folder_id)->toBe($folder->id);

    $this->actingAs($user)->delete(route('lists.destroy', $list))->assertRedirect(route('inbox'));
    $this->assertSoftDeleted('task_lists', ['id' => $list->id]);

    $this->actingAs($user)->delete(route('lists.destroy', $inbox))->assertForbidden();
    $this->assertDatabaseHas('task_lists', ['id' => $inbox->id, 'deleted_at' => null]);
});

it('moves a list placement without requiring or changing its name', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->for($user)->create();
    $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Keep this name']);

    $this->actingAs($user)
        ->post(route('lists.move', $list), ['folder_id' => $folder->id])
        ->assertSessionHasNoErrors();

    expect($list->fresh()->name)->toBe('Keep this name');
    expect(memberPlacement($list, $user)->folder_id)->toBe($folder->id);

    $this->actingAs($user)
        ->post(route('lists.move', $list), ['folder_id' => null])
        ->assertSessionHasNoErrors();

    expect(memberPlacement($list, $user)->folder_id)->toBeNull();
});

it('requires an explicit strategy when deleting a non-empty folder', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->for($user)->create();
    $list = TaskList::factory()->inFolder($folder)->create();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->from(route('inbox'))
        ->delete(route('folders.destroy', $folder))
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('domain');

    $this->assertDatabaseHas('folders', ['id' => $folder->id]);

    $this->actingAs($user)->delete(route('folders.destroy', $folder), ['lists' => 'detach'])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    $this->assertDatabaseHas('task_list_members', [
        'task_list_id' => $list->id,
        'user_id' => $user->id,
        'folder_id' => null,
    ]);
});

it('persists drag-and-drop task list and folder orders', function () {
    $user = User::factory()->create();
    $folderA = Folder::factory()->for($user)->create(['position' => 0]);
    $folderB = Folder::factory()->for($user)->create(['position' => 1]);
    $listA = TaskList::factory()->inFolder($folderA, 0)->create();
    $listB = TaskList::factory()->inFolder($folderA, 1)->create();
    $taskA = Task::factory()->forTaskList($listA)->create(['position' => 0]);
    $taskB = Task::factory()->forTaskList($listA)->create(['position' => 1]);

    $this->actingAs($user)->put(route('folders.order'), ['folder_ids' => [$folderB->id, $folderA->id]])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->put(route('lists.order'), [
        'folder_id' => $folderA->id,
        'task_list_ids' => [$listB->id, $listA->id],
    ])->assertSessionHasNoErrors();
    $this->actingAs($user)->put(route('lists.task-order', $listA), [
        'task_ids' => [$taskB->id, $taskA->id],
    ])->assertSessionHasNoErrors();

    expect($folderB->fresh()->position)->toBe(0)
        ->and($folderA->fresh()->position)->toBe(1)
        ->and(memberPlacement($listB, $user)->position)->toBe(0)
        ->and(memberPlacement($listA, $user)->position)->toBe(1)
        ->and($taskB->fresh()->position)->toBe(0)
        ->and($taskA->fresh()->position)->toBe(1);
});

it('prepends new tasks without disturbing the saved drag order or flashing reorder success', function () {
    $user = User::factory()->create();
    $list = TaskList::factory()->create(['user_id' => $user->id]);
    $first = Task::factory()->forTaskList($list)->create(['title' => 'First', 'position' => 0]);
    $second = Task::factory()->forTaskList($list)->create(['title' => 'Second', 'position' => 1]);

    $this->actingAs($user)
        ->from(route('lists.show', $list))
        ->put(route('lists.task-order', $list), [
            'task_ids' => [$second->id, $first->id],
        ])
        ->assertRedirect(route('lists.show', $list))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('success');

    $this->actingAs($user)
        ->post(route('lists.tasks.store', $list), ['title' => 'Newest'])
        ->assertSessionHasNoErrors();

    $newest = Task::query()
        ->where('task_list_id', $list->id)
        ->where('title', 'Newest')
        ->sole();

    expect($newest->position)->toBe(0)
        ->and($second->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2);

    $this->actingAs($user)->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.tasks.0.id', $newest->id)
            ->where('workspace.tasks.1.id', $second->id)
            ->where('workspace.tasks.2.id', $first->id));
});

it('rejects stale drag-and-drop orders and preserves canonical positions', function () {
    $user = User::factory()->create();
    $folderA = Folder::factory()->for($user)->create(['position' => 0]);
    $folderB = Folder::factory()->for($user)->create(['position' => 1]);
    $listA = TaskList::factory()->inFolder($folderA, 0)->create();
    $listB = TaskList::factory()->inFolder($folderA, 1)->create();
    $taskA = Task::factory()->forTaskList($listA)->create(['position' => 0]);
    $taskB = Task::factory()->forTaskList($listA)->create(['position' => 1]);

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $listA))
        ->put(route('folders.order'), ['folder_ids' => [$folderA->id]])
        ->assertRedirect(route('lists.show', $listA))
        ->assertSessionHasErrors('domain');

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $listA))
        ->put(route('lists.order'), [
            'folder_id' => $folderA->id,
            'task_list_ids' => [$listA->id],
        ])
        ->assertRedirect(route('lists.show', $listA))
        ->assertSessionHasErrors('domain');

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->from(route('lists.show', $listA))
        ->put(route('lists.task-order', $listA), ['task_ids' => [$taskA->id]])
        ->assertRedirect(route('lists.show', $listA))
        ->assertSessionHasErrors('domain');

    expect($folderA->fresh()->position)->toBe(0)
        ->and($folderB->fresh()->position)->toBe(1)
        ->and(memberPlacement($listA, $user)->position)->toBe(0)
        ->and(memberPlacement($listB, $user)->position)->toBe(1)
        ->and($taskA->fresh()->position)->toBe(0)
        ->and($taskB->fresh()->position)->toBe(1);
});

it('rejects invalid and cross-user web mutations', function () {
    $user = User::factory()->create();
    $foreignList = TaskList::factory()->create();
    $ownList = TaskList::factory()->create(['user_id' => $user->id]);
    $foreignTask = Task::factory()->forTaskList($foreignList)->create();

    $this->actingAs($user)->post(route('lists.tasks.store', $ownList), ['title' => '   '])
        ->assertSessionHasErrors('title');
    $this->actingAs($user)->post(route('lists.tasks.store', $foreignList), ['title' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($user)->post(route('tasks.complete', $foreignTask))
        ->assertForbidden();

    $this->assertDatabaseMissing('tasks', ['task_list_id' => $ownList->id, 'title' => '']);
});

it('allows unverified sanctum users to use the versioned api', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->getJson('/api/v1/inbox')->assertOk();
});
