<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_create_update_complete_restore_or_delete_subtasks(): void
    {
        $task = Task::factory()->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->getJson("/api/v1/tasks/{$task->id}/subtasks")->assertUnauthorized();
        $this->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Pack bags'])->assertUnauthorized();
        $this->putJson("/api/v1/subtasks/{$subtask->id}", ['title' => 'Renamed'])->assertUnauthorized();
        $this->postJson("/api/v1/subtasks/{$subtask->id}/complete")->assertUnauthorized();
        $this->postJson("/api/v1/subtasks/{$subtask->id}/restore")->assertUnauthorized();
        $this->deleteJson("/api/v1/subtasks/{$subtask->id}")->assertUnauthorized();
    }

    public function test_owner_can_create_a_subtask_under_a_task(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/subtasks", [
            'title' => '  Pack bags  ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Pack bags')
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonPath('data.task_id', $task->id);

        $this->assertDatabaseHas('subtasks', [
            'task_id' => $task->id,
            'title' => 'Pack bags',
            'is_completed' => false,
        ]);
    }

    public function test_blank_and_oversized_titles_are_rejected(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => str_repeat('a', 151)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('subtasks', 0);
    }

    public function test_subtasks_are_listed_oldest_first_and_included_with_task_details(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $newer = Subtask::factory()->forTask($task)->create(['title' => 'Second', 'created_at' => now()]);
        $older = Subtask::factory()->forTask($task)->create(['title' => 'First', 'created_at' => now()->subMinute()]);

        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}/subtasks")
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);

        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.subtasks.0.id', $older->id)
            ->assertJsonPath('data.subtasks.1.id', $newer->id);
    }

    public function test_owner_can_rename_complete_restore_and_delete_a_subtask(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create(['title' => 'Original', 'is_completed' => false]);

        $this->actingAs($user)->putJson("/api/v1/subtasks/{$subtask->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');

        $this->actingAs($user)->postJson("/api/v1/subtasks/{$subtask->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->actingAs($user)->postJson("/api/v1/subtasks/{$subtask->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_completed', false);

        $this->actingAs($user)->deleteJson("/api/v1/subtasks/{$subtask->id}")->assertNoContent();

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
    }

    public function test_another_user_cannot_read_create_update_or_delete_subtasks(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();

        $this->actingAs($stranger)->getJson("/api/v1/tasks/{$task->id}/subtasks")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Nope'])->assertForbidden();
        $this->actingAs($stranger)->putJson("/api/v1/subtasks/{$subtask->id}", ['title' => 'Nope'])->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/v1/subtasks/{$subtask->id}/complete")->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/v1/subtasks/{$subtask->id}")->assertForbidden();

        $this->assertDatabaseCount('subtasks', 1);
    }
}
