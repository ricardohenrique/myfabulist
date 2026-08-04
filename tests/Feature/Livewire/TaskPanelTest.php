<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Tasks\TaskPanel;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_task_persists_it_and_clears_the_input(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->set('newTaskTitle', 'Buy milk')
            ->call('addTask')
            ->assertSet('newTaskTitle', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Buy milk', 'task_list_id' => $list->id]);
    }

    public function test_a_blank_title_adds_a_validation_error_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->set('newTaskTitle', '   ')
            ->call('addTask')
            ->assertHasErrors('newTaskTitle');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_completing_a_task_moves_it_into_the_completed_group_and_sets_completed_at(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->call('completeTask', $task->id);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_restoring_a_task_reverses_completion(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->completed()->create();

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->call('restoreTask', $task->id);

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
    }

    public function test_inline_rename_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Old title']);

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->call('renameTask', $task->id, 'New title');

        $this->assertSame('New title', $task->fresh()->title);
    }

    public function test_delete_removes_the_row(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->call('deleteTask', $task->id);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_star_toggles(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['is_starred' => false]);

        $component = Livewire::actingAs($user)->test(TaskPanel::class, ['taskListId' => $list->id]);

        $component->call('toggleStar', $task->id);
        $this->assertTrue($task->fresh()->is_starred);

        $component->call('toggleStar', $task->id);
        $this->assertFalse($task->fresh()->is_starred);
    }

    public function test_a_component_instantiated_with_another_users_list_id_is_forbidden(): void
    {
        $stranger = User::factory()->create();
        $foreignList = TaskList::factory()->create();

        Livewire::actingAs($stranger)
            ->test(TaskPanel::class, ['taskListId' => $foreignList->id])
            ->assertStatus(403);
    }
}
