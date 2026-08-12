<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Folder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_factory_produces_a_valid_persisted_row(): void
    {
        $folder = Folder::factory()->create();

        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    }

    public function test_task_list_factory_produces_a_valid_persisted_row(): void
    {
        $list = TaskList::factory()->create();

        $this->assertDatabaseHas('task_lists', ['id' => $list->id]);
    }

    public function test_task_list_factory_in_folder_state(): void
    {
        $folder = Folder::factory()->create();
        $list = TaskList::factory()->inFolder($folder)->create();

        $this->assertDatabaseHas('task_lists', [
            'id' => $list->id,
            'folder_id' => $folder->id,
            'user_id' => $folder->user_id,
        ]);
    }

    public function test_task_list_factory_inbox_state(): void
    {
        $list = TaskList::factory()->inbox()->create();

        $this->assertDatabaseHas('task_lists', [
            'id' => $list->id,
            'name' => 'Inbox',
            'is_default' => true,
            'folder_id' => null,
        ]);
    }

    public function test_task_factory_produces_a_valid_persisted_row(): void
    {
        $task = Task::factory()->create();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertSame($task->taskList->user_id, $task->user_id);
    }

    public function test_task_comment_factory_for_task_state(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $comment = TaskComment::factory()->forTask($task, $user)->create();

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_subtask_factory_for_task_state(): void
    {
        $task = Task::factory()->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->assertDatabaseHas('subtasks', [
            'id' => $subtask->id,
            'task_id' => $task->id,
            'is_completed' => false,
        ]);
    }

    public function test_subtask_factory_completed_state(): void
    {
        $subtask = Subtask::factory()->completed()->create();

        $this->assertTrue($subtask->is_completed);
    }

    public function test_task_factory_for_task_list_state(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'task_list_id' => $list->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_task_factory_completed_state(): void
    {
        $task = Task::factory()->completed()->create();

        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_factory_starred_state(): void
    {
        $task = Task::factory()->starred()->create();

        $this->assertTrue($task->is_starred);
    }

    public function test_task_factory_with_note_state(): void
    {
        $task = Task::factory()->withNote()->create();

        $this->assertNotEmpty($task->note);
    }

    public function test_task_factory_due_today_state(): void
    {
        $task = Task::factory()->dueToday()->create();

        $this->assertSame('today', $task->dueDateStatus());
    }

    public function test_task_factory_overdue_state(): void
    {
        $task = Task::factory()->overdue()->create();

        $this->assertSame('overdue', $task->dueDateStatus());
    }

    public function test_task_factory_due_upcoming_state(): void
    {
        $task = Task::factory()->dueUpcoming()->create();

        $this->assertSame('upcoming', $task->dueDateStatus());
    }

    public function test_task_factory_overdue_completed_suppresses_overdue_status(): void
    {
        $task = Task::factory()->overdue()->completed()->create();

        $this->assertNull($task->dueDateStatus());
    }

    public function test_task_factory_completed_at_state(): void
    {
        $completedAt = now()->subDays(3);
        $task = Task::factory()->completedAt($completedAt)->create();

        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($completedAt->format('Y-m-d H:i:s'), $task->completed_at->format('Y-m-d H:i:s'));
    }
}
