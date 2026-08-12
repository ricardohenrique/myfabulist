<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $list = TaskList::factory()->create();

        $this->getJson("/api/v1/lists/{$list->id}/tasks")->assertStatus(401);
    }

    public function test_a_user_can_create_a_task_with_only_a_title(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/lists/{$list->id}/tasks", [
            'title' => 'Buy milk',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Buy milk');
        $this->assertDatabaseHas('tasks', ['title' => 'Buy milk', 'user_id' => $user->id]);
    }

    public function test_a_whitespace_only_title_is_rejected_and_nothing_persists(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/lists/{$list->id}/tasks", [
            'title' => '   ',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_creating_a_task_in_another_users_list_is_forbidden(): void
    {
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create();

        $response = $this->actingAs($stranger)->postJson("/api/v1/lists/{$list->id}/tasks", [
            'title' => 'Sneaky',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_index_returns_active_before_completed_ordered_by_most_recently_completed(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $active = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $olderCompleted = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()->subDay()]);
        $newerCompleted = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()]);

        $response = $this->actingAs($user)->getJson("/api/v1/lists/{$list->id}/tasks");

        $response->assertOk();
        $response->assertJsonPath('data.tasks.active.0.id', $active->id);
        $response->assertJsonPath('data.tasks.completed.0.id', $newerCompleted->id);
        $response->assertJsonPath('data.tasks.completed.1.id', $olderCompleted->id);
        $response->assertJsonPath('data.tasks.completed_count', 2);
    }

    public function test_index_does_not_incur_n_plus_one_queries(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($list)->count(5)->create();

        // Model::preventLazyLoading() (Step 1) turns any N+1 regression into
        // an exception, so a 200 here is itself the guarantee.
        $this->actingAs($user)->getJson("/api/v1/lists/{$list->id}/tasks")->assertOk();
    }

    public function test_complete_sets_is_completed_and_completed_at_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/complete");
        $response->assertOk();
        $response->assertJsonPath('data.is_completed', true);
        $this->assertNotNull($task->fresh()->completed_at);

        $completedAt = $task->fresh()->completed_at;

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/complete")->assertOk();
        $this->assertTrue($task->fresh()->completed_at->equalTo($completedAt));
    }

    public function test_restore_clears_is_completed_and_completed_at(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->completed()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/restore");

        $response->assertOk();
        $response->assertJsonPath('data.is_completed', false);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_update_replaces_note_due_date_and_star_and_clears_them_when_null(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred($user)->create([
            'note' => 'Old note',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Updated title',
            'note' => null,
            'due_date' => null,
            'is_starred' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_starred', false);
        $task->refresh();
        $this->assertSame('Updated title', $task->title);
        $this->assertNull($task->note);
        $this->assertNull($task->due_date);
        $this->assertDatabaseMissing('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);
    }

    /**
     * The empirical proof that the `{task}` route-binding fix (Plan 1,
     * Step 3) works end-to-end: a real HTTP round trip through
     * `PUT /api/v1/tasks/{task}`, reading `is_starred` back out of the
     * response body — not off a freshly re-queried model, which is exactly
     * what would hide a broken binding.
     */
    public function test_update_toggles_only_the_callers_own_star(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($owner)->putJson("/api/v1/tasks/{$task->id}", [
            'title' => $task->title,
            'is_starred' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_starred', true);
        $this->assertDatabaseHas('task_stars', ['task_id' => $task->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('task_stars', ['task_id' => $task->id, 'user_id' => $other->id]);
    }

    /**
     * Regression (Plan 1, Step 3 review): `TaskController::show()`'s
     * embedded `list` must carry the viewer's *real* placement —
     * `Task::taskList(): BelongsTo` never chains
     * `TaskList::scopeJoinMemberPlacement()` on its own, so before
     * `EloquentTaskRepository::loadDetails()` resolved this explicitly,
     * this endpoint silently emitted `folder_id`/`position: null` for a
     * task genuinely filed in a folder — the same class of bug Step 2's
     * review caught for `GET /api/v1/inbox`.
     */
    public function test_show_embeds_the_tasks_list_with_its_real_placement(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder, 2)->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}");

        $response->assertOk();
        $response->assertJsonPath('data.list.folder_id', $folder->id);
        $response->assertJsonPath('data.list.position', 2);
    }

    public function test_delete_removes_the_task_which_is_distinct_from_completing_it(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/tasks/{$task->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        // API contract stability: a soft-deleted task is indistinguishable
        // from a destroyed one across the V1 surface (Plan 4 NFR).
        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
    }

    public function test_move_to_another_of_the_users_lists_succeeds_and_keeps_user_id(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $targetList = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($sourceList)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/move", [
            'task_list_id' => $targetList->id,
        ]);

        $response->assertOk();
        $task->refresh();
        $this->assertSame($targetList->id, $task->task_list_id);
        $this->assertSame($user->id, $task->user_id);
    }

    /**
     * R4/D2 defence in depth: a trashed list id must be rejected by
     * validation before the request ever reaches the service.
     */
    public function test_move_to_a_deleted_list_fails_validation(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $deletedList = TaskList::factory()->create(['user_id' => $user->id]);
        $deletedList->delete();
        $task = Task::factory()->forTaskList($sourceList)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/move", [
            'task_list_id' => $deletedList->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame($sourceList->id, $task->fresh()->task_list_id);
    }

    public function test_move_to_a_foreign_list_fails_and_does_not_mutate(): void
    {
        $user = User::factory()->create();
        $sourceList = TaskList::factory()->create(['user_id' => $user->id]);
        $foreignList = TaskList::factory()->create();
        $task = Task::factory()->forTaskList($sourceList)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/move", [
            'task_list_id' => $foreignList->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame($sourceList->id, $task->fresh()->task_list_id);
    }

    public function test_task_order_rewrites_positions(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);

        $response = $this->actingAs($user)->putJson("/api/v1/lists/{$list->id}/task-order", [
            'task_ids' => [$b->id, $a->id],
        ]);

        $response->assertNoContent();
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_task_order_with_a_mismatched_id_set_returns_422_and_leaves_the_original_order_intact(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $foreignTask = Task::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/v1/lists/{$list->id}/task-order", [
            'task_ids' => [$a->id, $foreignTask->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'task_reorder_mismatch');
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }
}
