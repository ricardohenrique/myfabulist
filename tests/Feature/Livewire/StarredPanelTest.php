<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Tasks\StarredPanel;
use App\Livewire\Tasks\TaskDetails;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StarredPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_current_users_starred_tasks_appear(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($list)->starred()->create(['title' => 'My starred task']);

        $other = User::factory()->create();
        $otherList = TaskList::factory()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherList)->starred()->create(['title' => "Someone else's task"]);

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSee('My starred task')
            ->assertDontSee("Someone else's task");
    }

    public function test_tasks_from_multiple_lists_appear_together_with_correct_parent_names(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['name' => 'Work']);
        $listInFolder = TaskList::factory()->inFolder($folder)->create(['name' => 'Website launch']);
        $ungroupedList = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        Task::factory()->forTaskList($listInFolder)->starred()->create(['title' => 'Ship the launch email']);
        Task::factory()->forTaskList($ungroupedList)->starred()->create(['title' => 'Buy oat milk']);

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSee('Ship the launch email')
            ->assertSee('Work')
            ->assertSee('Website launch')
            ->assertSee('Buy oat milk')
            ->assertSee('Groceries');
    }

    public function test_unstarring_removes_the_row_and_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('unstarTask', $task->id);

        $this->assertFalse($task->fresh()->is_starred);
    }

    public function test_completing_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('completeTask', $task->id);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_restoring_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->completed()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('restoreTask', $task->id);

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
    }

    public function test_a_starred_completed_task_still_shows_its_star(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->completed()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSeeHtml("wire:click=\"toggleStar({$task->id})\"");
    }

    public function test_starred_rows_show_the_due_date_badge_and_the_note_indicator(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create([
            'note' => 'Bring the printed tickets',
            'due_date' => today(),
        ]);

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSeeHtml('data-due-status="today"')
            ->assertSeeHtml("data-test=\"note-indicator-{$task->id}\"");
    }

    public function test_opening_details_from_starred_works(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create(['title' => 'Confirm the venue']);

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('openDetails', $task->id)
            ->assertDispatched('task-details-open', taskId: $task->id);
    }

    public function test_unstarring_from_the_details_panel_removes_the_row_on_the_next_render(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create(['title' => 'Confirm the venue']);

        $starredPanel = Livewire::actingAs($user)->test(StarredPanel::class);
        $starredPanel->assertSee('Confirm the venue');

        Livewire::actingAs($user)
            ->test(TaskDetails::class)
            ->call('open', $task->id)
            ->set('isStarred', false)
            ->call('save');

        $starredPanel->dispatch('tasks-changed');
        $starredPanel->assertDontSee('Confirm the venue');
    }

    public function test_the_empty_state_renders_for_a_user_with_no_starred_tasks(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSee('No starred tasks yet.');
    }

    /**
     * D2's tripwire, at the Livewire layer: a deleted list's starred task
     * must not surface on the Starred page (it would also null-crash the
     * `taskList` relation the row renders without the whereHas guard).
     */
    public function test_a_starred_task_in_a_deleted_list_does_not_appear(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create(['title' => 'Ghost task']);

        $list->delete();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertDontSee('Ghost task');
    }

    public function test_it_does_not_incur_n_plus_one_queries(): void
    {
        $user = User::factory()->create();
        $listA = TaskList::factory()->create(['user_id' => $user->id]);
        $listB = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($listA)->starred()->count(3)->create();
        Task::factory()->forTaskList($listB)->starred()->count(3)->create();

        // Model::preventLazyLoading() (Step 1) turns any N+1 regression into
        // an exception, so a successful render here is itself the guarantee.
        Livewire::actingAs($user)->test(StarredPanel::class)->assertOk();
    }

    public function test_completing_sets_the_last_action_and_renders_the_undo_bar(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('completeTask', $task->id)
            ->assertSet('lastAction.type', 'complete')
            ->assertSeeHtml('data-test="undo-bar"');
    }

    public function test_undo_after_unstarring_restores_the_star(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('unstarTask', $task->id)
            ->assertSet('lastAction.type', 'star')
            ->call('undo');

        $this->assertTrue($task->fresh()->is_starred);
    }

    public function test_undo_after_moving_returns_the_task_to_its_original_list(): void
    {
        $user = User::factory()->create();
        $origin = TaskList::factory()->create(['user_id' => $user->id]);
        $destination = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($origin)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('moveTask', $task->id, $destination->id)
            ->call('undo');

        $this->assertSame($origin->id, $task->fresh()->task_list_id);
    }

    public function test_dismiss_undo_clears_the_state(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->call('completeTask', $task->id)
            ->call('dismissUndo')
            ->assertSet('lastAction', null)
            ->assertDontSeeHtml('data-test="undo-bar"');

        $this->assertTrue($task->fresh()->is_completed);
    }
}
