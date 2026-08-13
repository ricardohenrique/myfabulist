<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Exceptions\FolderReorderMismatchException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\FolderRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentFolderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private FolderRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(FolderRepositoryInterface::class);
    }

    public function test_all_for_user_returns_only_that_users_folders_ordered_by_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $second = Folder::factory()->for($user)->create(['position' => 1]);
        $first = Folder::factory()->for($user)->create(['position' => 0]);
        Folder::factory()->for($other)->create();

        $folders = $this->repository->allForUser($user);

        $this->assertSame([$first->id, $second->id], $folders->pluck('id')->all());
    }

    public function test_all_for_user_returns_an_empty_collection_when_the_user_has_no_folders(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->repository->allForUser($user)->isEmpty());
    }

    public function test_find_for_user_returns_null_for_another_users_folder(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();

        $this->assertNull($this->repository->findForUser($folder->id, $stranger));
        $this->assertTrue($owner->is($this->repository->findForUser($folder->id, $owner)->user));
    }

    public function test_next_position_on_an_empty_scope_is_zero(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->nextPosition($user));
    }

    public function test_next_position_on_a_populated_scope_continues_the_sequence(): void
    {
        $user = User::factory()->create();
        Folder::factory()->for($user)->create(['position' => 4]);

        $this->assertSame(5, $this->repository->nextPosition($user));
    }

    public function test_has_lists_reflects_whether_the_folder_holds_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();

        $this->assertFalse($this->repository->hasLists($folder));

        TaskList::factory()->inFolder($folder)->create(['name' => 'Work list']);

        $this->assertTrue($this->repository->hasLists($folder->fresh()));
    }

    public function test_delete_with_lists_removes_the_folder_its_lists_and_their_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create(['name' => 'Work list']);
        $task = Task::factory()->forTaskList($list)->create(['title' => 'A task']);

        $this->repository->deleteWithLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_detach_lists_moves_lists_to_ungrouped_then_deletes_the_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create(['name' => 'Work list']);

        $this->repository->detachLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $user->id,
            'folder_id' => null,
        ]);
    }

    /**
     * F11 (Plan 1, Step 6): `deleteWithLists()` must only ever force-delete
     * lists the folder's owner actually owns. A shared list they merely
     * hold an accepted membership on, filed into the same folder, is left
     * completely untouched — only their own membership placement is
     * ungrouped, exactly like `detachLists()`.
     */
    public function test_delete_with_lists_never_destroys_a_list_the_actor_does_not_own(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($sharedList)->create();
        $memberFolder = Folder::factory()->for($member)->create();
        TaskListMember::factory()->forTaskList($sharedList, $member)->create(['folder_id' => $memberFolder->id]);

        $this->repository->deleteWithLists($memberFolder);

        $this->assertDatabaseMissing('folders', ['id' => $memberFolder->id]);
        $this->assertDatabaseHas('task_lists', ['id' => $sharedList->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $member->id,
            'status' => 'accepted',
            'folder_id' => null,
        ]);
    }

    /**
     * The mixed case: a folder holding both a list the actor owns and a
     * shared list they do not. The owned one is destroyed exactly as
     * before; the shared one is left alone and only ungrouped for the actor.
     */
    public function test_delete_with_lists_force_deletes_only_the_owned_list_alongside_a_shared_one(): void
    {
        $actor = User::factory()->create();
        $otherOwner = User::factory()->create();
        $folder = Folder::factory()->for($actor)->create();
        $ownedList = TaskList::factory()->inFolder($folder)->create();
        $sharedList = TaskList::factory()->create(['user_id' => $otherOwner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $actor)->create(['folder_id' => $folder->id]);

        $this->repository->deleteWithLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $ownedList->id]);
        $this->assertDatabaseHas('task_lists', ['id' => $sharedList->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $actor->id,
            'status' => 'accepted',
            'folder_id' => null,
        ]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $otherOwner->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * `detachLists()` under the same shared-list-in-folder setup: an owned
     * list and a shared-but-not-owned list both get ungrouped, neither is
     * destroyed, and no other member's data is touched. Confirms
     * `detachLists()` needed no code change under sharing — it already
     * only ever nulls the actor's own membership `folder_id`, regardless of
     * who owns the list.
     */
    public function test_detach_lists_ungroups_an_owned_and_a_shared_but_not_owned_list_without_destroying_either(): void
    {
        $actor = User::factory()->create();
        $otherOwner = User::factory()->create();
        $folder = Folder::factory()->for($actor)->create();
        $ownedList = TaskList::factory()->inFolder($folder)->create();
        $sharedList = TaskList::factory()->create(['user_id' => $otherOwner->id]);
        TaskListMember::factory()->forTaskList($sharedList, $actor)->create(['folder_id' => $folder->id]);

        $this->repository->detachLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('task_lists', ['id' => $ownedList->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('task_lists', ['id' => $sharedList->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $ownedList->id,
            'user_id' => $actor->id,
            'folder_id' => null,
        ]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $actor->id,
            'folder_id' => null,
        ]);
        // The other owner's own membership row is untouched.
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $otherOwner->id,
            'status' => 'accepted',
        ]);
    }

    public function test_all_for_user_populates_each_folders_task_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create(['name' => 'Work list']);
        $emptyFolder = Folder::factory()->for($user)->create();

        $folders = $this->repository->allForUser($user);

        $foldered = $folders->first(fn (Folder $f): bool => $f->is($folder));
        $this->assertNotNull($foldered);
        $this->assertTrue($foldered->relationLoaded('taskLists'));
        $this->assertSame([$list->id], $foldered->taskLists->pluck('id')->all());

        $empty = $folders->first(fn (Folder $f): bool => $f->is($emptyFolder));
        $this->assertNotNull($empty);
        $this->assertTrue($empty->relationLoaded('taskLists'));
        $this->assertTrue($empty->taskLists->isEmpty());
    }

    /**
     * `Folder::taskLists()` is a real `belongsToMany` through
     * `task_list_members` (Plan 1, Step 2 review follow-up) — a plain
     * `$folder->load('taskLists')` works exactly like it did before the
     * columns were dropped from `task_lists`, including ordering by
     * position, so callers like `FolderController::show()` need no
     * repository passthrough for it.
     */
    public function test_folder_task_lists_relation_loads_lists_in_position_order(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $second = TaskList::factory()->inFolder($folder, 1)->create(['name' => 'Second']);
        $first = TaskList::factory()->inFolder($folder, 0)->create(['name' => 'First']);

        $loaded = $folder->load('taskLists');

        $this->assertTrue($loaded->relationLoaded('taskLists'));
        $this->assertSame([$first->id, $second->id], $loaded->taskLists->pluck('id')->all());
        $this->assertSame($folder->id, $loaded->taskLists->first()->folder_id);
        $this->assertSame(0, $loaded->taskLists->first()->position);
    }

    public function test_apply_order_rewrites_positions(): void
    {
        $user = User::factory()->create();
        $a = Folder::factory()->for($user)->create(['position' => 0]);
        $b = Folder::factory()->for($user)->create(['position' => 1]);

        $this->repository->applyOrder($user, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_apply_order_rejects_an_incomplete_or_duplicate_folder_set_without_writing(): void
    {
        $user = User::factory()->create();
        $a = Folder::factory()->for($user)->create(['position' => 0]);
        $b = Folder::factory()->for($user)->create(['position' => 1]);

        try {
            $this->repository->applyOrder($user, [$a->id, $a->id]);
            $this->fail('Expected a folder reorder mismatch.');
        } catch (FolderReorderMismatchException $exception) {
            $this->assertSame('folder_reorder_mismatch', $exception->errorCode());
        }

        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }
}
