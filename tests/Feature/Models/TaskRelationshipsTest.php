<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Folder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskComment;
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

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Architecture Decision 4:
     * `creator()` is an identical relation to `user()`, added so new call
     * sites can name the attribution intent explicitly. Constructed
     * directly via factories (no real sharing needed) — a task whose
     * `user_id` differs from its list's `user_id` still resolves `creator()`
     * correctly, proving the relation reads `tasks.user_id` and not
     * `task_lists.user_id`.
     */
    public function test_creator_resolves_the_creating_user_even_when_different_from_the_lists_owner(): void
    {
        $owner = User::factory()->create();
        $creator = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create(['user_id' => $creator->id]);

        $this->assertTrue($creator->is($task->creator));
        $this->assertFalse($owner->is($task->creator));
        $this->assertTrue($task->user->is($task->creator));
    }

    /**
     * A plain Eloquent ->delete() (not the repository's detachLists()) still
     * leaves the list itself untouched and nulls its placement — this is
     * task_list_members.folder_id's nullOnDelete() FK doing the work now
     * (Plan 1, Step 2), not a FK on task_lists itself (that column is gone).
     */
    public function test_deleting_a_folder_detaches_its_lists_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();

        $folder->delete();

        $this->assertDatabaseHas('task_lists', ['id' => $list->id]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $user->id,
            'folder_id' => null,
        ]);
    }

    public function test_a_task_comment_belongs_to_its_task_and_author(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $comment = TaskComment::factory()->forTask($task, $user)->create();

        $this->assertTrue($task->is($comment->task));
        $this->assertTrue($user->is($comment->author));
        $this->assertTrue($task->comments->contains($comment));
        $this->assertTrue($user->taskComments->contains($comment));
    }

    public function test_force_deleting_a_task_cascades_its_comments(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $comment = TaskComment::factory()->forTask($task, $user)->create();

        $task->forceDelete();

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_a_subtask_belongs_to_its_task(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->assertTrue($task->is($subtask->task));
        $this->assertTrue($task->subtasks->contains($subtask));
    }

    public function test_force_deleting_a_task_cascades_its_subtasks(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $task->forceDelete();

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
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
