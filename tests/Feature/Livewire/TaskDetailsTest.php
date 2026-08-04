<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Tasks\TaskDetails;
use App\Livewire\Tasks\TaskPanel;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_hydrates_the_form_from_the_task(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create([
            'title' => 'Ship the launch email',
            'note' => 'Double-check the subject line',
            'due_date' => today()->addDays(3),
        ]);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->assertSet('title', 'Ship the launch email')
            ->assertSet('note', 'Double-check the subject line')
            ->assertSet('isStarred', true)
            ->assertSet('dueDate', $task->due_date->format('Y-m-d'));
    }

    public function test_saving_persists_title_note_and_star(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Old title', 'note' => null]);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('title', 'New title')
            ->set('note', 'A helpful note')
            ->set('isStarred', true)
            ->call('save');

        $task->refresh();
        $this->assertSame('New title', $task->title);
        $this->assertSame('A helpful note', $task->note);
        $this->assertTrue($task->is_starred);
    }

    public function test_clearing_the_note_persists_null(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['note' => 'Some note']);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('note', '')
            ->call('save');

        $this->assertNull($task->fresh()->note);
    }

    public function test_a_blank_title_fails_validation_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Keep me']);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('title', '   ')
            ->call('save')
            ->assertHasErrors('title');

        $this->assertSame('Keep me', $task->fresh()->title);
    }

    public function test_a_5001_character_note_fails_validation(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['note' => null]);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('note', str_repeat('a', 5001))
            ->call('save')
            ->assertHasErrors('note');

        $this->assertNull($task->fresh()->note);
    }

    public function test_another_users_task_id_is_refused(): void
    {
        $stranger = User::factory()->create();
        $foreignTask = Task::factory()->create();

        Livewire::actingAs($stranger)
            ->test(TaskDetails::class)
            ->call('open', $foreignTask->id)
            ->assertStatus(404);
    }

    public function test_delete_removes_the_task_and_closes_the_panel(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->call('delete')
            ->assertSet('taskId', null);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_tasks_changed_causes_the_task_panel_to_re_render(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Original title']);

        $panel = Livewire::actingAs($user)->test(TaskPanel::class, ['taskListId' => $list->id]);
        $panel->assertSee('Original title');

        $task->update(['title' => 'Renamed elsewhere']);

        $panel->dispatch('tasks-changed');
        $panel->assertSee('Renamed elsewhere');
    }

    public function test_setting_a_due_date_persists_it_and_the_row_renders_the_badge(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['due_date' => null]);
        $dueDate = today()->addDays(5)->toDateString();

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('dueDate', $dueDate)
            ->call('save');

        $this->assertTrue($task->fresh()->due_date->isSameDay($dueDate));

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->assertSeeHtml('data-due-status="upcoming"');
    }

    public function test_clearing_the_due_date_persists_null_and_removes_the_badge(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['due_date' => today()->addDay()]);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('dueDate', null)
            ->call('save');

        $this->assertNull($task->fresh()->due_date);

        Livewire::actingAs($user)
            ->test(TaskPanel::class, ['taskListId' => $list->id])
            ->assertDontSeeHtml('data-due-status');
    }

    public function test_an_invalid_date_string_fails_validation(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['due_date' => null]);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('dueDate', 'not-a-date')
            ->call('save')
            ->assertHasErrors('dueDate');

        $this->assertNull($task->fresh()->due_date);
    }

    public function test_changing_the_destination_list_on_save_moves_the_task_and_dispatches_navigation_changed(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $destination = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'Ship it']);

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('title', 'Ship it')
            ->set('taskListId', $destination->id)
            ->call('save')
            ->assertDispatched('navigation-changed');

        $this->assertSame($destination->id, $task->fresh()->task_list_id);
    }

    public function test_a_note_containing_a_script_tag_renders_escaped(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create(['note' => '<script>alert(1)</script>']);

        $component = Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id);

        $component->assertDontSeeHtml('<script>alert(1)</script>');
        $component->assertSee('<script>alert(1)</script>');
    }
}
