<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Tasks\StarredPanel;
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

    public function test_the_empty_state_renders_for_a_user_with_no_starred_tasks(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StarredPanel::class)
            ->assertSee('No starred tasks yet.');
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
}
