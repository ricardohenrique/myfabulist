<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Navigation;

use App\Livewire\Navigation\FolderDialog;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FolderDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_folder_persists_it_and_dispatches_navigation_changed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'create', folderId: null)
            ->set('name', 'Work')
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseHas('folders', ['user_id' => $user->id, 'name' => 'Work']);
    }

    public function test_a_blank_name_fails_validation_and_persists_nothing(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'create', folderId: null)
            ->set('name', '   ')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('folders', 0);
    }

    public function test_a_256_character_name_fails_validation_and_persists_nothing(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'create', folderId: null)
            ->set('name', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('folders', 0);
    }

    public function test_renaming_persists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['name' => 'Old name']);

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'rename', folderId: $folder->id)
            ->set('name', 'New name')
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertSame('New name', $folder->fresh()->name);
    }

    public function test_deleting_an_empty_folder_removes_it(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'delete', folderId: $folder->id)
            ->call('deleteEmptyFolder')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    }

    public function test_moving_lists_out_deletes_the_folder_and_leaves_lists_and_tasks_intact(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'delete', folderId: $folder->id)
            ->call('moveListsOut')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('task_lists', ['id' => $list->id, 'folder_id' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_deleting_with_lists_removes_the_lists_and_their_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'delete', folderId: $folder->id)
            ->call('deleteFolderAndLists')
            ->assertDispatched('navigation-changed');

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_opening_the_dialog_for_another_users_folder_is_denied(): void
    {
        $user = User::factory()->create();
        $foreignFolder = Folder::factory()->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class)
            ->dispatch('folder-dialog-open', mode: 'rename', folderId: $foreignFolder->id)
            ->assertStatus(404);
    }

    public function test_deleting_the_folder_containing_the_open_list_redirects_to_the_inbox(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();

        Livewire::actingAs($user)
            ->test(FolderDialog::class, ['currentTaskListId' => $list->id])
            ->dispatch('folder-dialog-open', mode: 'delete', folderId: $folder->id)
            ->call('deleteFolderAndLists')
            ->assertRedirect(route('inbox'));
    }

    public function test_deleting_the_folder_not_containing_the_open_list_does_not_redirect(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folder)->create();
        $otherList = TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(FolderDialog::class, ['currentTaskListId' => $otherList->id])
            ->dispatch('folder-dialog-open', mode: 'delete', folderId: $folder->id)
            ->call('deleteFolderAndLists')
            ->assertNoRedirect();
    }
}
