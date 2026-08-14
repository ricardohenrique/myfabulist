<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\NotListOwnerException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_throws_when_the_referenced_folder_does_not_belong_to_the_user(): void
    {
        $user = User::factory()->create();
        $foreignFolder = Folder::factory()->create();
        $service = app(TaskListService::class);

        $this->expectException(FolderNotFoundException::class);

        $service->create($user, 'Sneaky', $foreignFolder->id);
    }

    public function test_update_throws_when_moving_to_a_folder_that_does_not_belong_to_the_user(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $foreignFolder = Folder::factory()->create();
        $service = app(TaskListService::class);

        $this->expectException(FolderNotFoundException::class);

        $service->update($list, $user, 'Renamed', $foreignFolder->id);
    }

    public function test_delete_throws_for_the_default_list(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $service = app(TaskListService::class);

        $this->expectException(DefaultTaskListCannotBeDeletedException::class);

        $service->delete($inbox, $user);
    }

    /**
     * F10/F12 (Plan 1, Step 6): an accepted, non-owner member can never
     * delete a shared list outright — that would soft-delete it for every
     * member, not just themselves. Only `leave()` (Step 5) is available to
     * a non-owner; `delete()` must reject them regardless of what any
     * controller/Policy layer already checked.
     */
    public function test_delete_throws_when_the_actor_is_an_accepted_member_but_not_the_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();
        $service = app(TaskListService::class);

        $this->expectException(NotListOwnerException::class);

        try {
            $service->delete($list, $member);
        } finally {
            $this->assertNull($list->fresh()->deleted_at);
        }
    }

    public function test_delete_succeeds_for_the_owner_of_a_non_default_list(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $service = app(TaskListService::class);

        $service->delete($list, $owner);

        $this->assertNotNull($list->fresh()->deleted_at);
    }

    public function test_update_forces_the_default_list_to_stay_ungrouped(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $folder = Folder::factory()->for($user)->create();
        $service = app(TaskListService::class);

        $updated = $service->update($inbox, $user, 'Inbox renamed', $folder->id);

        $this->assertNull($updated->folder_id);
        $this->assertSame('Inbox renamed', $updated->name);
    }

    /**
     * Plan 4/Step 5: undeleting a list holding a completed task and a
     * starred task returns both to the right sections and back to Starred —
     * the list-level delete/undelete is orthogonal to per-task state (D2).
     */
    public function test_undelete_returns_the_list_and_all_of_its_tasks_including_completed_and_starred_state(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $active = Task::factory()->forTaskList($list)->create();
        $completed = Task::factory()->forTaskList($list)->completed()->create();
        $starred = Task::factory()->forTaskList($list)->starred()->create();
        $listService = app(TaskListService::class);
        $taskService = app(TaskService::class);

        $listService->delete($list, $user);
        $undeleted = $listService->undelete($list);

        $this->assertNull($undeleted->deleted_at);
        $tasks = $taskService->tasksFor($undeleted, $user);
        $this->assertTrue($tasks->active->contains(fn (Task $t): bool => $t->is($active)));
        $this->assertTrue($tasks->completed->contains(fn (Task $t): bool => $t->is($completed)));
        $this->assertTrue($taskService->starredFor($user)->contains(fn (Task $t): bool => $t->is($starred)));
    }
}
