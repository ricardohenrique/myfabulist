<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Navigation;

use App\Livewire\Navigation\ListDialog;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_ungrouped_list_persists_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'create', listId: null, folderId: null)
            ->set('name', 'Groceries')
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseHas('task_lists', [
            'user_id' => $user->id,
            'name' => 'Groceries',
            'folder_id' => null,
        ]);
    }

    public function test_creating_a_list_inside_a_folder_persists_it_nested(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'create', listId: null, folderId: $folder->id)
            ->set('name', 'Website launch')
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseHas('task_lists', [
            'user_id' => $user->id,
            'name' => 'Website launch',
            'folder_id' => $folder->id,
        ]);
    }

    public function test_renaming_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Old name']);

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'rename', listId: $list->id, folderId: null)
            ->set('name', 'New name')
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertSame('New name', $list->fresh()->name);
    }

    public function test_moving_a_list_into_a_folder_and_back_out_does_not_lose_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);
        Task::factory()->forTaskList($list)->count(3)->create();

        $this->assertSame(3, $list->tasks()->count());

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'rename', listId: $list->id, folderId: null)
            ->set('folderId', $folder->id)
            ->call('save');

        $this->assertSame($folder->id, $list->fresh()->folder_id);
        $this->assertSame(3, $list->fresh()->tasks()->count());

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'rename', listId: $list->id, folderId: $folder->id)
            ->set('folderId', null)
            ->call('save');

        $this->assertNull($list->fresh()->folder_id);
        $this->assertSame(3, $list->fresh()->tasks()->count());
    }

    public function test_deleting_removes_the_list_and_its_tasks(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'delete', listId: $list->id, folderId: null)
            ->call('delete')
            ->assertDispatched('navigation-changed');

        $this->assertSoftDeleted('task_lists', ['id' => $list->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_deleting_the_currently_open_list_redirects_to_the_inbox(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ListDialog::class, ['currentTaskListId' => $list->id])
            ->dispatch('list-dialog-open', mode: 'delete', listId: $list->id, folderId: null)
            ->call('delete')
            ->assertRedirect(route('inbox'));
    }

    public function test_deleting_a_list_that_is_not_open_does_not_redirect(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $otherList = TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ListDialog::class, ['currentTaskListId' => $otherList->id])
            ->dispatch('list-dialog-open', mode: 'delete', listId: $list->id, folderId: null)
            ->call('delete')
            ->assertNoRedirect();
    }

    public function test_the_inbox_cannot_be_deleted_and_the_attempt_does_not_500(): void
    {
        $user = User::factory()->create();
        $inbox = app(TaskListService::class)->inboxFor($user);

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->set('listId', $inbox->id)
            ->set('mode', 'delete')
            ->call('delete')
            ->assertForbidden();

        $this->assertDatabaseHas('task_lists', ['id' => $inbox->id]);
    }

    public function test_opening_the_dialog_for_another_users_list_is_denied(): void
    {
        $user = User::factory()->create();
        $foreignList = TaskList::factory()->create();

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'rename', listId: $foreignList->id, folderId: null)
            ->assertStatus(404);
    }

    public function test_moving_to_a_foreign_folder_id_surfaces_a_toast_instead_of_a_500(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $foreignFolder = Folder::factory()->create();

        Livewire::actingAs($user)
            ->test(ListDialog::class)
            ->dispatch('list-dialog-open', mode: 'rename', listId: $list->id, folderId: null)
            ->set('folderId', $foreignFolder->id)
            ->call('save')
            ->assertOk()
            ->assertNotDispatched('navigation-changed');

        $this->assertNull($list->fresh()->folder_id);
    }
}
