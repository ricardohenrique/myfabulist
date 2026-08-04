<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Navigation;

use App\Livewire\Navigation\Sidebar;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\FolderService;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_folders_and_their_lists_render_with_names(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['name' => 'Work']);
        TaskList::factory()->inFolder($folder)->create(['name' => 'Website launch']);
        TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee('Work');
        $response->assertSee('Website launch');
        $response->assertSee('Groceries');
    }

    public function test_another_users_folders_and_lists_do_not_render(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $strangerFolder = Folder::factory()->for($stranger)->create(['name' => 'Stranger Folder']);
        TaskList::factory()->inFolder($strangerFolder)->create(['name' => 'Stranger List']);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertDontSee('Stranger Folder');
        $response->assertDontSee('Stranger List');
    }

    public function test_the_inbox_badge_shows_the_active_task_count(): void
    {
        $user = User::factory()->create();
        $inbox = app(TaskListService::class)->inboxFor($user);
        Task::factory()->forTaskList($inbox)->count(2)->create();
        Task::factory()->forTaskList($inbox)->completed()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSeeInOrder(['href="'.route('inbox').'"', '>2<'], false);
    }

    public function test_the_inbox_badge_is_absent_when_there_are_no_active_tasks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertDontSee('>0<', false);
    }

    public function test_navigation_changed_refreshes_the_tree_after_a_list_is_created_out_of_band(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Sidebar::class);
        $component->assertDontSee('Freshly added');

        app(TaskListService::class)->create($user, 'Freshly added', null);

        $component->dispatch('navigation-changed');
        $component->assertSee('Freshly added');
    }

    public function test_the_sidebar_renders_without_a_lazy_loading_violation(): void
    {
        $user = User::factory()->create();
        $folderA = Folder::factory()->for($user)->create();
        $folderB = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folderA)->count(2)->create();
        TaskList::factory()->inFolder($folderB)->count(2)->create();
        TaskList::factory()->create(['user_id' => $user->id]);
        TaskList::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)->test(Sidebar::class)->assertOk();
    }

    public function test_a_guest_is_forbidden(): void
    {
        Livewire::test(Sidebar::class)->assertStatus(403);
    }

    public function test_a_folder_created_via_the_service_appears_after_the_tree_is_refreshed(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Sidebar::class);
        $component->assertDontSee('New Folder');

        app(FolderService::class)->create($user, 'New Folder');

        $component->dispatch('navigation-changed');
        $component->assertSee('New Folder');
    }

    public function test_a_list_item_links_to_its_show_route(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee('href="'.route('lists.show', $list).'"', false);
    }

    public function test_the_current_list_is_marked_data_current_when_mounted_on_its_own_route(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Website launch']);

        $response = $this->actingAs($user)->get(route('lists.show', $list));

        $response->assertOk();
        $response->assertSeeInOrder([
            'href="'.route('lists.show', $list).'"',
            'data-current="data-current"',
        ], false);
    }

    public function test_a_brand_new_user_sees_the_empty_state_copy_and_its_ctas(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee('data-test="navigation-empty-state"', false);
        $response->assertSee('Create your first list');
        $response->assertSee('Create a folder');
    }

    public function test_a_user_with_lists_does_not_see_the_empty_state(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertDontSee('data-test="navigation-empty-state"', false);
    }

    public function test_moving_a_list_down_within_its_folder_persists_and_does_not_touch_another_folders_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $first = TaskList::factory()->inFolder($folder)->create(['position' => 1]);
        $second = TaskList::factory()->inFolder($folder)->create(['position' => 2]);

        $otherFolder = Folder::factory()->for($user)->create();
        $otherFirst = TaskList::factory()->inFolder($otherFolder)->create(['position' => 1]);
        $otherSecond = TaskList::factory()->inFolder($otherFolder)->create(['position' => 2]);

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('moveListDown', $first->id);

        $this->assertSame(
            [$second->id, $first->id],
            TaskList::query()->where('folder_id', $folder->id)->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame(
            [$otherFirst->id, $otherSecond->id],
            TaskList::query()->where('folder_id', $otherFolder->id)->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_moving_an_ungrouped_list_up_persists_and_never_moves_the_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = app(TaskListService::class)->inboxFor($user);
        $first = TaskList::factory()->create(['user_id' => $user->id, 'position' => 1]);
        $second = TaskList::factory()->create(['user_id' => $user->id, 'position' => 2]);

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('moveListUp', $second->id);

        $this->assertSame(
            [$second->id, $first->id],
            TaskList::query()->where('user_id', $user->id)->whereNull('folder_id')->where('is_default', false)->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame(0, $inbox->fresh()->position);
    }

    public function test_dragging_a_list_to_a_new_position_persists_it_through_the_drag_entry_point(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $first = TaskList::factory()->inFolder($folder)->create(['position' => 1]);
        $second = TaskList::factory()->inFolder($folder)->create(['position' => 2]);
        $third = TaskList::factory()->inFolder($folder)->create(['position' => 3]);

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('reorderList', $first->id, 2, $folder->id);

        $this->assertSame(
            [$second->id, $third->id, $first->id],
            TaskList::query()->where('folder_id', $folder->id)->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_dragging_a_list_into_a_folder_it_does_not_belong_to_is_refused_and_nothing_is_written(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $first = TaskList::factory()->inFolder($folder)->create(['position' => 1]);
        $second = TaskList::factory()->inFolder($folder)->create(['position' => 2]);

        $otherFolder = Folder::factory()->for($user)->create();
        $foreignList = TaskList::factory()->inFolder($otherFolder)->create(['position' => 1]);

        // A stale/hand-crafted drop names a list that is not actually in the
        // target folder's own group (C6) — reorderList() refuses it before
        // any write, exactly like a cross-folder drag would (R6).
        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('reorderList', $foreignList->id, 1, $folder->id)
            ->assertOk();

        $this->assertSame(
            [$first->id, $second->id],
            TaskList::query()->where('folder_id', $folder->id)->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame($otherFolder->id, $foreignList->fresh()->folder_id);
    }

    public function test_folder_reordering_persists(): void
    {
        $user = User::factory()->create();
        $first = Folder::factory()->for($user)->create(['position' => 1]);
        $second = Folder::factory()->for($user)->create(['position' => 2]);

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('moveFolderUp', $second->id);

        $this->assertSame(
            [$second->id, $first->id],
            Folder::query()->where('user_id', $user->id)->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_reordering_a_foreign_folder_id_is_refused(): void
    {
        $user = User::factory()->create();
        $first = Folder::factory()->for($user)->create(['position' => 1]);
        $second = Folder::factory()->for($user)->create(['position' => 2]);

        $stranger = User::factory()->create();
        $strangerFolder = Folder::factory()->for($stranger)->create();

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('moveFolderUp', $strangerFolder->id)
            ->assertNotFound();

        $this->assertSame(
            [$first->id, $second->id],
            Folder::query()->where('user_id', $user->id)->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_the_tree_re_renders_a_folder_in_its_new_position_after_reordering(): void
    {
        $user = User::factory()->create();
        $first = Folder::factory()->for($user)->create(['position' => 1, 'name' => 'Personal']);
        $second = Folder::factory()->for($user)->create(['position' => 2, 'name' => 'Work']);

        Livewire::actingAs($user)
            ->test(Sidebar::class)
            ->call('moveFolderUp', $second->id)
            ->assertSeeHtmlInOrder(['>Work<', '>Personal<']);
    }

    /**
     * Step 5 visual pass: the starter-kit "Repository"/"Documentation" links
     * are noise in a product UI (frontend.md's "Avoid" list) and are removed
     * from the sidebar footer.
     */
    public function test_the_starter_kit_footer_links_are_not_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertDontSee('Repository');
        $response->assertDontSee('Documentation');
    }
}
