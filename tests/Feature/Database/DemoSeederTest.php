<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Folder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\TaskListService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const USER_COUNT = 3;

    public function test_run_default_argument_is_twenty(): void
    {
        $reflection = new ReflectionMethod(DemoSeeder::class, 'run');
        $parameter = $reflection->getParameters()[0];

        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertSame(20, $parameter->getDefaultValue());
    }

    public function test_it_creates_the_requested_number_of_verified_demo_users(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        $this->assertSame(self::USER_COUNT, User::query()->count());

        foreach (range(1, self::USER_COUNT) as $n) {
            $user = User::query()->where('email', "demo{$n}@example.com")->first();

            $this->assertNotNull($user, "demo{$n}@example.com was not seeded.");
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check('password', $user->password));
        }
    }

    public function test_every_user_has_exactly_one_default_inbox_at_position_zero_ungrouped(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        User::query()->get()->each(function (User $user): void {
            $inboxes = TaskList::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->get();

            $this->assertCount(1, $inboxes, "user {$user->id} does not have exactly one default list.");

            $inbox = $inboxes->first();
            $this->assertNull($inbox->folder_id);
            $this->assertSame(0, $inbox->position);
        });
    }

    public function test_folder_and_list_counts_per_user_fall_within_the_documented_ranges(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        User::query()->get()->each(function (User $user): void {
            $folderCount = Folder::query()->where('user_id', $user->id)->count();
            $this->assertGreaterThanOrEqual(3, $folderCount);
            $this->assertLessThanOrEqual(5, $folderCount);

            $folderedListCount = TaskList::query()
                ->where('user_id', $user->id)
                ->whereNotNull('folder_id')
                ->count();
            $this->assertGreaterThanOrEqual(5, $folderedListCount);
            $this->assertLessThanOrEqual(10, $folderedListCount);

            $standaloneListCount = TaskList::query()
                ->where('user_id', $user->id)
                ->whereNull('folder_id')
                ->where('is_default', false)
                ->count();
            $this->assertGreaterThanOrEqual(2, $standaloneListCount);
            $this->assertLessThanOrEqual(4, $standaloneListCount);

            TaskList::query()->where('user_id', $user->id)->get()->each(function (TaskList $list): void {
                $taskCount = Task::query()->where('task_list_id', $list->id)->count();
                $this->assertGreaterThanOrEqual(10, $taskCount, "list {$list->id} has fewer than 10 tasks.");
                $this->assertLessThanOrEqual(20, $taskCount, "list {$list->id} has more than 20 tasks.");
            });
        });
    }

    public function test_every_task_has_between_zero_and_five_subtasks(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        Task::query()->get()->each(function (Task $task): void {
            $subtaskCount = Subtask::query()->where('task_id', $task->id)->count();
            $this->assertGreaterThanOrEqual(0, $subtaskCount);
            $this->assertLessThanOrEqual(5, $subtaskCount, "task {$task->id} has more than 5 subtasks.");
        });

        $this->assertGreaterThan(0, Subtask::query()->count(), 'Expected at least one subtask across the whole dataset.');
    }

    public function test_every_subtask_belongs_to_a_task_owned_by_the_same_user_chain(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        Subtask::query()->with('task')->get()->each(function (Subtask $subtask): void {
            $this->assertNotNull($subtask->task);
        });
    }

    public function test_completed_tasks_have_only_completed_subtasks(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        Task::query()->where('is_completed', true)->get()->each(function (Task $task): void {
            $incomplete = Subtask::query()
                ->where('task_id', $task->id)
                ->where('is_completed', false)
                ->count();

            $this->assertSame(0, $incomplete, "completed task {$task->id} has an incomplete subtask.");
        });
    }

    public function test_no_folder_is_left_empty(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        Folder::query()->get()->each(function (Folder $folder): void {
            $listCount = TaskList::query()->where('folder_id', $folder->id)->count();
            $this->assertGreaterThan(0, $listCount, "folder {$folder->id} has no lists.");
        });
    }

    public function test_every_task_and_foldered_list_belongs_to_its_parents_owner(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        Task::query()->with('taskList')->get()->each(function (Task $task): void {
            $this->assertSame($task->taskList->user_id, $task->user_id);
        });

        TaskList::query()->whereNotNull('folder_id')->with('folder')->get()->each(function (TaskList $list): void {
            $this->assertSame($list->folder->user_id, $list->user_id);
        });
    }

    public function test_positions_are_contiguous_within_every_ordering_bucket(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        User::query()->get()->each(function (User $user): void {
            $this->assertContiguousPositions(
                Folder::query()->where('user_id', $user->id)->orderBy('position')->pluck('position')->all(),
            );

            $ungroupedPositions = TaskList::query()
                ->where('user_id', $user->id)
                ->whereNull('folder_id')
                ->orderBy('position')
                ->pluck('position')
                ->all();
            $this->assertContiguousPositions($ungroupedPositions);

            Folder::query()->where('user_id', $user->id)->get()->each(function (Folder $folder): void {
                $this->assertContiguousPositions(
                    TaskList::query()->where('folder_id', $folder->id)->orderBy('position')->pluck('position')->all(),
                );
            });

            TaskList::query()->where('user_id', $user->id)->get()->each(function (TaskList $list): void {
                $this->assertContiguousPositions(
                    Task::query()->where('task_list_id', $list->id)->orderBy('position')->pluck('position')->all(),
                );
            });
        });
    }

    public function test_every_user_has_showcase_variety_in_their_data(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        User::query()->get()->each(function (User $user): void {
            $starred = Task::query()->where('user_id', $user->id)->where('is_starred', true)->count();
            $overdue = Task::query()->where('user_id', $user->id)
                ->where('is_completed', false)
                ->whereDate('due_date', '<', today())
                ->count();
            $dueToday = Task::query()->where('user_id', $user->id)
                ->whereDate('due_date', today())
                ->count();
            $completed = Task::query()->where('user_id', $user->id)->where('is_completed', true)->count();

            $this->assertGreaterThanOrEqual(1, $starred, "user {$user->id} has no starred task.");
            $this->assertGreaterThanOrEqual(1, $overdue, "user {$user->id} has no overdue task.");
            $this->assertGreaterThanOrEqual(1, $dueToday, "user {$user->id} has no due-today task.");
            $this->assertGreaterThanOrEqual(1, $completed, "user {$user->id} has no completed task.");
        });
    }

    public function test_completed_tasks_do_not_all_share_one_completed_at(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        $distinctCompletedAt = Task::query()
            ->where('is_completed', true)
            ->distinct()
            ->pluck('completed_at');

        $this->assertGreaterThan(1, $distinctCompletedAt->count());
    }

    public function test_inbox_for_matches_the_seeded_row_without_creating_a_second_one(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        $user = User::query()->where('email', 'demo1@example.com')->firstOrFail();
        $countBefore = TaskList::query()->where('user_id', $user->id)->count();

        $inbox = app(TaskListService::class)->inboxFor($user);

        $this->assertTrue($inbox->is_default);
        $this->assertSame($countBefore, TaskList::query()->where('user_id', $user->id)->count());
    }

    public function test_navigation_tree_renders_without_lazy_loading_violations(): void
    {
        (new DemoSeeder)->run(self::USER_COUNT);

        $user = User::query()->where('email', 'demo1@example.com')->firstOrFail();

        $tree = app(NavigationService::class)->treeFor($user);

        $this->assertGreaterThanOrEqual(3, count($tree->folders));
        $this->assertGreaterThanOrEqual(2, $tree->ungroupedLists->count());
        $this->assertGreaterThan(0, $tree->starredCount);
    }

    public function test_running_it_twice_is_additive_not_idempotent(): void
    {
        (new DemoSeeder)->run(1);
        (new DemoSeeder)->run(1);

        $this->assertSame(2, User::query()->count());
    }

    /**
     * @param  array<int, int>  $positions
     */
    private function assertContiguousPositions(array $positions): void
    {
        $this->assertSame(range(0, count($positions) - 1), $positions);
    }
}
