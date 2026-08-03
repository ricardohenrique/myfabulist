<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Exceptions\TaskReorderMismatchException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TaskRepositoryInterface::class);
    }

    public function test_active_for_list_excludes_completed_tasks_and_orders_by_position(): void
    {
        $list = TaskList::factory()->create();
        $second = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $first = Task::factory()->forTaskList($list)->create(['position' => 0]);
        Task::factory()->forTaskList($list)->completed()->create();

        $active = $this->repository->activeForList($list);

        $this->assertSame([$first->id, $second->id], $active->pluck('id')->all());
    }

    public function test_active_for_list_returns_an_empty_collection_when_there_are_no_active_tasks(): void
    {
        $list = TaskList::factory()->create();

        $this->assertTrue($this->repository->activeForList($list)->isEmpty());
    }

    public function test_completed_for_list_orders_by_most_recently_completed_first(): void
    {
        $list = TaskList::factory()->create();
        $older = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()->subDay()]);
        $newer = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()]);

        $completed = $this->repository->completedForList($list);

        $this->assertSame([$newer->id, $older->id], $completed->pluck('id')->all());
    }

    public function test_starred_for_user_spans_lists_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $listA = TaskList::factory()->create(['user_id' => $user->id]);
        $listB = TaskList::factory()->create(['user_id' => $user->id]);
        $starredA = Task::factory()->forTaskList($listA)->starred()->create();
        $starredB = Task::factory()->forTaskList($listB)->starred()->create();
        Task::factory()->forTaskList($listA)->create();

        $other = User::factory()->create();
        $otherList = TaskList::factory()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherList)->starred()->create();

        $starred = $this->repository->starredForUser($user);

        $this->assertSame(
            [$starredA->id, $starredB->id],
            $starred->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_find_for_user_returns_null_for_another_users_task(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id]);

        $this->assertNull($this->repository->findForUser($task->id, $stranger));
        $this->assertNotNull($this->repository->findForUser($task->id, $owner));
    }

    public function test_next_position_on_an_empty_list_is_zero(): void
    {
        $list = TaskList::factory()->create();

        $this->assertSame(0, $this->repository->nextPosition($list));
    }

    public function test_next_position_on_a_populated_list_continues_the_sequence(): void
    {
        $list = TaskList::factory()->create();
        Task::factory()->forTaskList($list)->create(['position' => 2]);

        $this->assertSame(3, $this->repository->nextPosition($list));
    }

    public function test_mark_completed_sets_is_completed_and_completed_at_and_is_idempotent(): void
    {
        $task = Task::factory()->create();

        $this->repository->markCompleted($task);
        $task->refresh();

        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);

        $completedAt = $task->completed_at;

        $this->repository->markCompleted($task);
        $task->refresh();

        $this->assertTrue($task->completed_at->equalTo($completedAt));
    }

    public function test_mark_active_clears_is_completed_and_completed_at_and_is_idempotent(): void
    {
        $task = Task::factory()->completed()->create();

        $this->repository->markActive($task);
        $task->refresh();

        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);

        $this->repository->markActive($task);
        $task->refresh();

        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
    }

    public function test_apply_order_rewrites_positions_to_match_the_submitted_order(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);

        $this->repository->applyOrder($list, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_apply_order_rolls_back_and_throws_on_a_mismatched_id_set(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $foreignTask = Task::factory()->create();

        $this->expectException(TaskReorderMismatchException::class);

        try {
            $this->repository->applyOrder($list, [$a->id, $foreignTask->id]);
        } finally {
            $this->assertSame(0, $a->fresh()->position);
            $this->assertSame(1, $b->fresh()->position);
        }
    }

    public function test_apply_order_throws_when_an_id_is_missing_from_the_submitted_set(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        Task::factory()->forTaskList($list)->create(['position' => 1]);

        $this->expectException(TaskReorderMismatchException::class);

        $this->repository->applyOrder($list, [$a->id]);
    }
}
