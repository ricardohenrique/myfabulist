<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/lists')->assertStatus(401);
    }

    public function test_a_user_can_create_an_ungrouped_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/lists', ['name' => 'Groceries']);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Groceries');
        $response->assertJsonPath('data.folder_id', null);
    }

    public function test_a_user_can_create_a_list_inside_a_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/lists', [
            'name' => 'Website launch',
            'folder_id' => $folder->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.folder_id', $folder->id);
    }

    public function test_creating_a_list_referencing_another_users_folder_fails_validation_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $foreignFolder = Folder::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/lists', [
            'name' => 'Sneaky',
            'folder_id' => $foreignFolder->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('task_lists', ['name' => 'Sneaky']);
    }

    public function test_a_user_can_rename_a_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

        $response = $this->actingAs($user)->putJson("/api/v1/lists/{$list->id}", ['name' => 'New']);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New');
    }

    public function test_moving_a_list_between_folders_keeps_its_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'folder_id' => null]);
        Task::factory()->forTaskList($list)->count(3)->create();

        $response = $this->actingAs($user)->putJson("/api/v1/lists/{$list->id}", [
            'name' => $list->name,
            'folder_id' => $folder->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.folder_id', $folder->id);
        $this->assertSame(3, $list->tasks()->count());
    }

    public function test_moving_a_list_out_of_a_folder_keeps_its_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        Task::factory()->forTaskList($list)->count(2)->create();

        $response = $this->actingAs($user)->putJson("/api/v1/lists/{$list->id}", [
            'name' => $list->name,
            'folder_id' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.folder_id', null);
        $this->assertSame(2, $list->tasks()->count());
    }

    public function test_deleting_a_list_soft_deletes_it_and_hides_it_and_its_tasks_everywhere(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $starredTask = Task::factory()->forTaskList($list)->starred()->create();
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/lists/{$list->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('task_lists', ['id' => $list->id]);

        // Deleting a list does not touch its tasks (D2) — the rows survive
        // physically but become invisible everywhere the list is deleted.
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('tasks', ['id' => $starredTask->id]);

        $this->actingAs($user)->getJson("/api/v1/lists/{$list->id}")->assertNotFound();
        $this->actingAs($user)->getJson('/api/v1/lists')
            ->assertJsonMissing(['id' => $list->id]);
        $this->actingAs($user)->getJson('/api/v1/starred')
            ->assertJsonMissing(['id' => $starredTask->id]);
    }

    public function test_deleting_the_inbox_is_rejected(): void
    {
        // TaskListPolicy::delete() denies the default list as defence in
        // depth (D5/Step 2) — the request never reaches TaskListService,
        // so it is blocked at authorization (403) rather than surfacing the
        // service's DefaultTaskListCannotBeDeletedException. That exception
        // path is exercised directly in TaskListServiceTest.
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/lists/{$inbox->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('task_lists', ['id' => $inbox->id]);
    }

    public function test_a_user_cannot_access_another_users_list(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->getJson("/api/v1/lists/{$list->id}")->assertForbidden();
        $this->actingAs($stranger)->putJson("/api/v1/lists/{$list->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/v1/lists/{$list->id}")->assertForbidden();
    }

    /**
     * R4/D2 defence in depth: a trashed list id in the reorder payload is
     * rejected by validation rather than silently writing nothing.
     */
    public function test_put_lists_order_rejects_a_deleted_list_id(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $a = TaskList::factory()->inFolder($folder)->create(['position' => 0]);
        $deleted = TaskList::factory()->inFolder($folder)->create(['position' => 1]);
        $deleted->delete();

        $response = $this->actingAs($user)->putJson('/api/v1/lists/order', [
            'folder_id' => $folder->id,
            'task_list_ids' => [$deleted->id, $a->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $a->fresh()->position);
    }

    public function test_put_lists_order_reorders_lists_within_a_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $a = TaskList::factory()->inFolder($folder)->create(['position' => 0]);
        $b = TaskList::factory()->inFolder($folder)->create(['position' => 1]);

        $response = $this->actingAs($user)->putJson('/api/v1/lists/order', [
            'folder_id' => $folder->id,
            'task_list_ids' => [$b->id, $a->id],
        ]);

        $response->assertNoContent();
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }
}
