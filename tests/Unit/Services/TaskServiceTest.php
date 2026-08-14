<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidTaskTitleException;
use App\Exceptions\TaskListNotFoundException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\TaskService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests (mocked repositories, no database) for the trim/blank-
 * title invariant (M3) and the target-list resolution invariant (D7),
 * proving these rules live in the Service and not in a delivery mechanism.
 *
 * `move()`'s success path and its Step 6/Q8 sharing-boundary guard are
 * deliberately *not* unit-tested here any more: both require
 * `Task::loadMissing('taskList')`, and Eloquent's `loadMissing()`
 * unconditionally builds a query object (to introspect eager-load keys)
 * before it ever checks whether the relation is already loaded — so it
 * needs a real, bootstrapped DB connection resolver even when the relation
 * was pre-set via `setRelation()`. This plain `PHPUnit\Framework\TestCase`
 * boots no Laravel app at all. Those cases are covered, more realistically,
 * as Feature tests against a real database in
 * `Tests\Feature\Services\TaskServiceTest`.
 */
class TaskServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_trims_the_title_before_persisting(): void
    {
        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $taskList = new TaskList(['name' => 'Inbox']);

        $tasks = Mockery::mock(TaskRepositoryInterface::class);
        $tasks->shouldReceive('nextPosition')->once()->with($taskList)->andReturn(0);
        $tasks->shouldReceive('create')
            ->once()
            ->with($user, $taskList, 'Buy milk', 0)
            ->andReturn(new Task(['title' => 'Buy milk']));

        $service = new TaskService(
            $tasks,
            Mockery::mock(TaskListRepositoryInterface::class),
            Mockery::mock(TaskListMemberRepositoryInterface::class),
        );

        $task = $service->create($user, $taskList, '  Buy milk  ');

        $this->assertSame('Buy milk', $task->title);
    }

    public function test_create_throws_when_the_trimmed_title_is_blank(): void
    {
        $service = new TaskService(
            Mockery::mock(TaskRepositoryInterface::class),
            Mockery::mock(TaskListRepositoryInterface::class),
            Mockery::mock(TaskListMemberRepositoryInterface::class),
        );

        $this->expectException(InvalidTaskTitleException::class);

        $service->create(new User, new TaskList, '   ');
    }

    public function test_rename_throws_when_the_trimmed_title_is_blank(): void
    {
        $service = new TaskService(
            Mockery::mock(TaskRepositoryInterface::class),
            Mockery::mock(TaskListRepositoryInterface::class),
            Mockery::mock(TaskListMemberRepositoryInterface::class),
        );

        $this->expectException(InvalidTaskTitleException::class);

        $service->rename(new Task, "\t \n");
    }

    public function test_move_throws_when_the_target_list_does_not_belong_to_the_user(): void
    {
        $user = new User;
        $task = new Task;

        $taskLists = Mockery::mock(TaskListRepositoryInterface::class);
        $taskLists->shouldReceive('findOwnedBy')->once()->with(99, $user)->andReturnNull();

        // The guard's countAcceptedFor() checks are never reached — target
        // resolution fails first — so this mock has no expectations set.
        $service = new TaskService(
            Mockery::mock(TaskRepositoryInterface::class),
            $taskLists,
            Mockery::mock(TaskListMemberRepositoryInterface::class),
        );

        $this->expectException(TaskListNotFoundException::class);

        $service->move($task, $user, 99, null);
    }
}
