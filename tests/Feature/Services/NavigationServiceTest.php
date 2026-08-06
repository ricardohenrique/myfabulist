<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_exposes_the_inbox_and_excludes_it_from_ungrouped_lists(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertTrue($tree->inbox->is($inbox));
        $this->assertFalse($tree->ungroupedLists->contains(fn (TaskList $list): bool => $list->is($inbox)));
    }

    public function test_folders_come_back_in_position_order_with_their_lists_in_position_order(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $second = Folder::factory()->for($user)->create(['position' => 1]);
        $first = Folder::factory()->for($user)->create(['position' => 0]);
        $secondList = TaskList::factory()->inFolder($first)->create(['position' => 1]);
        $firstList = TaskList::factory()->inFolder($first)->create(['position' => 0]);

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertSame([$first->id, $second->id], collect($tree->folders)->map(fn ($nf) => $nf->folder->id)->all());
        $this->assertSame(
            [$firstList->id, $secondList->id],
            $tree->folders[0]->lists->pluck('id')->all(),
        );
    }

    public function test_a_folder_with_no_lists_yields_an_empty_collection_not_missing(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        Folder::factory()->for($user)->create();

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertCount(1, $tree->folders);
        $this->assertTrue($tree->folders[0]->lists->isEmpty());
    }

    public function test_another_users_folders_and_lists_never_appear(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $stranger = User::factory()->create();
        Folder::factory()->for($stranger)->create();
        TaskList::factory()->create(['user_id' => $stranger->id]);

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertCount(0, $tree->folders);
        $this->assertTrue($tree->ungroupedLists->isEmpty());
    }

    public function test_active_tasks_count_counts_only_incomplete_tasks(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($list)->create();
        Task::factory()->forTaskList($list)->completed()->create();

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertSame(1, $tree->ungroupedLists->first()->active_tasks_count);
    }

    public function test_inbox_active_tasks_count_reflects_only_incomplete_tasks(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($inbox)->create();
        Task::factory()->forTaskList($inbox)->completed()->create();

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertSame(1, $tree->inbox->active_tasks_count);
    }

    public function test_starred_count_reflects_only_this_users_starred_tasks(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($inbox)->starred()->create();
        Task::factory()->forTaskList($inbox)->starred()->create();
        Task::factory()->forTaskList($inbox)->create();

        $other = User::factory()->create();
        $otherInbox = TaskList::factory()->inbox()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherInbox)->starred()->create();

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertSame(2, $tree->starredCount);
    }

    public function test_a_user_with_no_folders_and_no_extra_lists_still_gets_a_valid_tree(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertTrue($tree->inbox->is_default);
        $this->assertSame([], $tree->folders);
        $this->assertTrue($tree->ungroupedLists->isEmpty());
    }

    public function test_the_tree_is_built_in_a_bounded_number_of_queries(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folder)->count(3)->create();
        TaskList::factory()->create(['user_id' => $user->id]);

        DB::enableQueryLog();

        app(NavigationService::class)->treeFor($user);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 6 queries regardless of folder/list count: inbox lookup, folders,
        // folders' eager-loaded taskLists, lists, lists' eager-loaded folder,
        // starred count. The two eager loads are unused by this read model
        // but come from the existing repository contracts (allForUser())
        // shared with other callers, so they are not removed here — the
        // assertion still pins the count and would fail if a change ever
        // made it grow with N.
        $this->assertLessThanOrEqual(6, $queryCount);
    }
}
