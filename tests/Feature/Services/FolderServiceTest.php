<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\FolderNotEmptyException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Services\FolderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_appends_the_next_position_and_trims_the_name(): void
    {
        $user = User::factory()->create();
        $service = app(FolderService::class);

        $first = $service->create($user, '  Work  ');
        $second = $service->create($user, 'Personal');

        $this->assertSame('Work', $first->name);
        $this->assertSame(0, $first->position);
        $this->assertSame(1, $second->position);
    }

    public function test_delete_throws_when_the_folder_has_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folder)->create();
        $service = app(FolderService::class);

        $this->expectException(FolderNotEmptyException::class);

        $service->delete($folder);
    }

    public function test_delete_succeeds_for_an_empty_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $service = app(FolderService::class);

        $service->delete($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    }

    public function test_delete_with_lists_removes_the_folder_and_its_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $service = app(FolderService::class);

        $service->deleteWithLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
    }

    /**
     * F11 (Plan 1, Step 6) — the reason this step exists. Before Step 5,
     * nobody could hold an accepted membership on a list they did not own,
     * so `deleteWithLists()`'s old unscoped `forceDelete()` was accidentally
     * safe. Now that `ListSharingService::accept()` is real: a member
     * accepts a share on the owner's list, files it into their own folder,
     * then destroys that folder with the destructive strategy. The owner's
     * list and every one of its tasks must be completely intact afterwards —
     * only the member's own folder and their own placement of the list
     * (ungrouped) are affected.
     */
    public function test_delete_with_lists_never_destroys_a_shared_list_the_actor_does_not_own(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($sharedList)->create();
        $memberFolder = Folder::factory()->for($member)->create();
        TaskListMember::factory()->forTaskList($sharedList, $member)->create(['folder_id' => $memberFolder->id]);
        $service = app(FolderService::class);

        $service->deleteWithLists($memberFolder);

        // The member's own folder is gone.
        $this->assertDatabaseMissing('folders', ['id' => $memberFolder->id]);

        // The owner's list and its task are completely intact — not even
        // soft-deleted.
        $this->assertDatabaseHas('task_lists', ['id' => $sharedList->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);

        // The member is still an accepted member — just ungrouped, since
        // their folder is gone. Their membership row was never deleted.
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $member->id,
            'status' => 'accepted',
            'folder_id' => null,
        ]);

        // The owner's own membership row was never touched.
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $owner->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * F12 (Plan 1, Step 6): even a list the actor genuinely *owns* is not
     * unconditionally destroyed by the folder-bulk delete path. An owned,
     * unshared list is still hard-deleted exactly as before (regression).
     * An owned list that is shared (a collaborator has accepted membership
     * on it) must be soft-deleted instead — the same recoverable outcome
     * `TaskListService::delete()` already guarantees on the single-list
     * path — otherwise the folder-bulk path would be a second, easier way
     * for an owner to irrecoverably destroy every collaborator's copy of a
     * shared list.
     */
    public function test_delete_with_lists_soft_deletes_an_owned_shared_list_but_hard_deletes_an_owned_unshared_one(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();
        $unsharedList = TaskList::factory()->inFolder($folder)->create();
        $sharedList = TaskList::factory()->inFolder($folder)->create();
        TaskListMember::factory()->forTaskList($sharedList, $collaborator)->create();
        $unsharedTask = Task::factory()->forTaskList($unsharedList)->create();
        $sharedTask = Task::factory()->forTaskList($sharedList)->create();
        $service = app(FolderService::class);

        $service->deleteWithLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);

        // The unshared list is truly gone — hard-deleted, cascading to its
        // task, exactly like before this fix (regression).
        $this->assertDatabaseMissing('task_lists', ['id' => $unsharedList->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $unsharedTask->id]);

        // The shared list is soft-deleted, not destroyed — still present,
        // still recoverable, its task untouched.
        $this->assertSoftDeleted('task_lists', ['id' => $sharedList->id]);
        $this->assertDatabaseHas('tasks', ['id' => $sharedTask->id, 'deleted_at' => null]);

        // Every accepted member's membership row for the shared list
        // survives untouched, including the owner's own — access, not
        // existence, is what a soft delete changes.
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $owner->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $sharedList->id,
            'user_id' => $collaborator->id,
            'status' => 'accepted',
        ]);
    }

    public function test_detach_lists_moves_lists_to_ungrouped(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $service = app(FolderService::class);

        $service->detachLists($folder);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $user->id,
            'folder_id' => null,
        ]);
    }
}
