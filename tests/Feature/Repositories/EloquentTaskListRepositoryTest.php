<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskListRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskListRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TaskListRepositoryInterface::class);
    }

    public function test_all_for_user_returns_only_that_users_lists_ordered_by_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $second = TaskList::factory()->create(['user_id' => $user->id, 'position' => 1]);
        $first = TaskList::factory()->create(['user_id' => $user->id, 'position' => 0]);
        TaskList::factory()->create(['user_id' => $other->id]);

        $lists = $this->repository->allForUser($user);

        $this->assertSame([$first->id, $second->id], $lists->pluck('id')->all());
    }

    public function test_all_for_user_returns_an_empty_collection_when_the_user_has_no_lists(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->repository->allForUser($user)->isEmpty());
    }

    public function test_find_for_user_returns_null_for_another_users_list(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertNull($this->repository->findForUser($list->id, $stranger));
        $this->assertNotNull($this->repository->findForUser($list->id, $owner));
    }

    public function test_create_default_for_is_the_sole_way_the_inbox_is_provisioned(): void
    {
        $user = User::factory()->create();

        $inbox = $this->repository->createDefaultFor($user);

        $this->assertTrue($inbox->is_default);
        $this->assertNull($inbox->folder_id);
        $this->assertSame('Inbox', $inbox->name);
        $this->assertTrue($inbox->is($this->repository->findDefaultFor($user)));
    }

    public function test_find_default_for_returns_null_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->repository->findDefaultFor($user));
    }

    public function test_next_position_on_an_empty_scope_is_zero(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->nextPosition($user, null));
    }

    public function test_next_position_is_scoped_per_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->create(['user_id' => $user->id, 'folder_id' => null, 'position' => 3]);
        TaskList::factory()->inFolder($folder)->create(['position' => 1]);

        $this->assertSame(4, $this->repository->nextPosition($user, null));
        $this->assertSame(2, $this->repository->nextPosition($user, $folder->id));
    }

    public function test_update_can_move_a_list_between_folders(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'folder_id' => null]);

        $this->repository->update($list, 'Renamed', $folder, 0);

        $this->assertSame('Renamed', $list->fresh()->name);
        $this->assertSame($folder->id, $list->fresh()->folder_id);
    }

    public function test_apply_order_rewrites_positions_within_a_folder_scope(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $a = TaskList::factory()->inFolder($folder)->create(['position' => 0]);
        $b = TaskList::factory()->inFolder($folder)->create(['position' => 1]);

        $this->repository->applyOrder($user, $folder->id, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }
}
