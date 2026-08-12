<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
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

        $service->delete($inbox);
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

        $listService->delete($list);
        $undeleted = $listService->undelete($list);

        $this->assertNull($undeleted->deleted_at);
        $tasks = $taskService->tasksFor($undeleted, $user);
        $this->assertTrue($tasks->active->contains(fn (Task $t): bool => $t->is($active)));
        $this->assertTrue($tasks->completed->contains(fn (Task $t): bool => $t->is($completed)));
        $this->assertTrue($taskService->starredFor($user)->contains(fn (Task $t): bool => $t->is($starred)));
    }
}
