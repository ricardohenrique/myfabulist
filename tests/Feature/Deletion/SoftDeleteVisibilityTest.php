<?php

declare(strict_types=1);

namespace Tests\Feature\Deletion;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\FolderService;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan 4, Step 6: the invariant sweep — one assertion per rule this plan
 * introduces, named after the rule it protects. This class is the
 * deliverable: it must fail if the SoftDeletes trait, the `whereHas`
 * Starred guard, the `forceDelete()` folder fix, or the `whereNull`
 * validation fix is ever reverted (verified by hand while writing it).
 */
class SoftDeleteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pins the SoftDeletes trait on Task itself (D1): a deleted task must be
     * *recoverable*, not merely gone — a plain hard delete would satisfy
     * every other assertion in this class by accident.
     */
    public function test_a_deleted_task_is_recoverable_not_destroyed(): void
    {
        $task = Task::factory()->create();

        app(TaskService::class)->delete($task);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    /**
     * Pins the SoftDeletes trait on TaskList (D1) the same way.
     */
    public function test_a_deleted_list_is_recoverable_not_destroyed(): void
    {
        $list = TaskList::factory()->create();

        app(TaskListService::class)->delete($list);

        $this->assertSoftDeleted('task_lists', ['id' => $list->id]);
    }

    public function test_a_deleted_task_is_absent_from_the_list_panels_active_section(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        app(TaskService::class)->delete($task);

        $this->assertFalse(app(TaskService::class)->tasksFor($list, $user)->active->contains(fn (Task $t): bool => $t->is($task)));
    }

    public function test_a_deleted_task_is_absent_from_the_list_panels_completed_section(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->completed()->create();

        app(TaskService::class)->delete($task);

        $this->assertFalse(app(TaskService::class)->tasksFor($list, $user)->completed->contains(fn (Task $t): bool => $t->is($task)));
    }

    public function test_a_deleted_task_is_absent_from_starred(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        app(TaskService::class)->delete($task);

        $this->assertFalse(app(TaskService::class)->starredFor($user)->contains(fn (Task $t): bool => $t->is($task)));
    }

    public function test_a_deleted_task_is_absent_from_the_lists_tasks_count(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        app(TaskService::class)->delete($task);

        $found = app(TaskListRepositoryInterface::class)->allForUser($user)->firstOrFail();
        $this->assertSame(0, $found->tasks_count);
    }

    public function test_a_deleted_task_is_absent_from_ids_for_list(): void
    {
        $list = TaskList::factory()->create();
        $task = Task::factory()->forTaskList($list)->create();

        app(TaskService::class)->delete($task);

        $this->assertTrue(app(TaskRepositoryInterface::class)->idsForList($list)->isEmpty());
    }

    public function test_a_deleted_task_returns_404_from_the_api(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        app(TaskService::class)->delete($task);

        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
    }

    public function test_a_deleted_list_is_absent_from_all_for_user(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        app(TaskListService::class)->delete($list);

        $this->assertFalse(app(TaskListRepositoryInterface::class)->allForUser($user)->contains(fn (TaskList $l): bool => $l->is($list)));
    }

    public function test_a_deleted_list_is_absent_from_get_lists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        app(TaskListService::class)->delete($list);

        $this->actingAs($user)->getJson('/api/v1/lists')->assertJsonMissing(['id' => $list->id]);
    }

    public function test_a_deleted_list_returns_404_from_the_api(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        app(TaskListService::class)->delete($list);

        $this->actingAs($user)->getJson("/api/v1/lists/{$list->id}")->assertNotFound();
    }

    public function test_a_deleted_list_is_rejected_as_a_move_target(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $deletedList = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($sourceList)->create();

        app(TaskListService::class)->delete($deletedList);

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/move", [
            'task_list_id' => $deletedList->id,
        ])->assertStatus(422);
    }

    public function test_a_deleted_list_is_rejected_in_a_reorder_payload(): void
    {
        $user = User::factory()->create();
        $keep = TaskList::factory()->atPosition(0)->create(['user_id' => $user->id]);
        $deletedList = TaskList::factory()->atPosition(1)->create(['user_id' => $user->id]);

        app(TaskListService::class)->delete($deletedList);

        $this->actingAs($user)->putJson('/api/v1/lists/order', [
            'folder_id' => null,
            'task_list_ids' => [$deletedList->id, $keep->id],
        ])->assertStatus(422);
    }

    /**
     * D2's tripwire — the single most likely future regression: the tasks
     * of a deleted list must not leak into Starred.
     */
    public function test_the_tasks_of_a_deleted_list_are_absent_from_starred(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $starredTask = Task::factory()->forTaskList($list)->starred()->create();

        app(TaskListService::class)->delete($list);

        $this->assertFalse(app(TaskService::class)->starredFor($user)->contains(fn (Task $t): bool => $t->is($starredTask)));
        $this->actingAs($user)->getJson('/api/v1/starred')->assertJsonMissing(['id' => $starredTask->id]);
    }

    public function test_the_inbox_cannot_be_deleted_by_the_service(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $this->expectException(DefaultTaskListCannotBeDeletedException::class);

        app(TaskListService::class)->delete($inbox);
    }

    public function test_the_inbox_cannot_be_deleted_through_the_api(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/v1/lists/{$inbox->id}")->assertForbidden();
        $this->assertNull($inbox->fresh()->deleted_at);
    }

    /**
     * D5's tripwire — folder deletion "with lists" must still be a real,
     * physical delete: the lists and their tasks are destroyed, not merely
     * trashed and left unreachable.
     */
    public function test_deleting_a_folder_with_lists_physically_destroys_the_lists_and_their_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $task = Task::factory()->forTaskList($list)->create();

        app(FolderService::class)->deleteWithLists($folder);

        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /**
     * D8: no purge job exists, so trashed rows persist until the account
     * itself is deleted — at which point the `users` → `tasks`/`task_lists`
     * cascadeOnDelete FKs physically purge them regardless of `deleted_at`.
     */
    public function test_deleting_the_account_physically_purges_soft_deleted_tasks_and_lists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $task->delete();
        $list->delete();

        $user->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
    }

    /**
     * API contract stability (NFR): DELETE stays 204, a later GET stays 404,
     * and no V1 resource ever exposes `deleted_at`.
     */
    public function test_the_v1_task_delete_contract_is_unchanged(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->actingAs($user)->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();
        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();

        $live = Task::factory()->forTaskList($list)->create();
        $this->actingAs($user)->getJson("/api/v1/tasks/{$live->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.deleted_at');
    }

    public function test_the_v1_list_delete_contract_is_unchanged(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/v1/lists/{$list->id}")->assertNoContent();
        $this->actingAs($user)->getJson("/api/v1/lists/{$list->id}")->assertNotFound();

        $live = TaskList::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->getJson("/api/v1/lists/{$live->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.deleted_at');
    }
}
