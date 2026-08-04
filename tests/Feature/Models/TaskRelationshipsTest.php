<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_users_folder_list_task_chain_persists_and_reads_back(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['name' => 'Work']);
        $list = TaskList::factory()->inFolder($folder)->create(['name' => 'Website launch']);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Draft copy']);

        $task->refresh();

        $this->assertTrue($user->is($task->user));
        $this->assertTrue($folder->is($list->folder));
        $this->assertTrue($list->is($task->taskList));
        $this->assertTrue($user->folders->contains($folder));
        $this->assertTrue($user->taskLists->contains($list));
        $this->assertTrue($user->tasks->contains($task));
        $this->assertTrue($folder->taskLists->contains($list));
        $this->assertTrue($list->tasks->contains($task));
    }

    public function test_deleting_a_folder_detaches_its_lists_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();

        $folder->delete();

        $this->assertDatabaseHas('task_lists', [
            'id' => $list->id,
            'folder_id' => null,
        ]);
    }

    public function test_soft_deleting_a_list_leaves_its_tasks_intact_but_hidden(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $list->delete();

        $this->assertSoftDeleted('task_lists', ['id' => $list->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull($task->fresh()->deleted_at);
        $this->assertTrue(TaskList::query()->whereKey($list->id)->doesntExist());
    }

    /**
     * Proves the FK cascade still does its job when a list is *actually*
     * destroyed — the only path that reaches this now is folder deletion
     * (D5's forceDelete()), not the ordinary list-delete flow above.
     */
    public function test_force_deleting_a_list_still_cascades_its_tasks(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $list->forceDelete();

        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_deleting_a_user_removes_folders_lists_and_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $task = Task::factory()->forTaskList($list)->create();

        // A soft-deleted task and a soft-deleted list — account deletion
        // must physically purge these trashed rows too (D8/Plan 4), not
        // just the live ones. `users.id` cascadeOnDelete is a real FK
        // DELETE, which reaches every row regardless of `deleted_at`.
        $trashedList = TaskList::factory()->create(['user_id' => $user->id]);
        $trashedList->delete();
        $trashedTask = Task::factory()->forTaskList($list)->create();
        $trashedTask->delete();

        $user->delete();

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $trashedList->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $trashedTask->id]);
    }
}
