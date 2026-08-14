<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/folders')->assertStatus(401);
    }

    public function test_a_user_can_create_a_folder(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/folders', ['name' => 'Work']);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Work');
        $this->assertDatabaseHas('folders', ['user_id' => $user->id, 'name' => 'Work']);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/folders', ['name' => '   ']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('folders', 0);
    }

    public function test_an_oversized_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/folders', ['name' => str_repeat('a', 256)]);

        $response->assertStatus(422);
    }

    public function test_a_user_can_list_their_folders(): void
    {
        $user = User::factory()->create();
        Folder::factory()->for($user)->create();
        Folder::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/folders');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_user_can_view_a_folder_with_its_lists(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folder)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/folders/{$folder->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.lists');
    }

    public function test_a_user_can_rename_a_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->putJson("/api/v1/folders/{$folder->id}", ['name' => 'New name']);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $this->assertSame('New name', $folder->fresh()->name);
    }

    public function test_deleting_an_empty_folder_returns_204(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/folders/{$folder->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    }

    public function test_deleting_a_non_empty_folder_without_a_strategy_returns_409(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->inFolder($folder)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/folders/{$folder->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'folder_not_empty');
        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    }

    public function test_deleting_with_detach_moves_lists_out_and_survives(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/folders/{$folder->id}?lists=detach");

        $response->assertNoContent();
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $list->id,
            'user_id' => $user->id,
            'folder_id' => null,
        ]);
    }

    public function test_deleting_with_delete_removes_lists_and_their_tasks(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder)->create();
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/folders/{$folder->id}?lists=delete");

        $response->assertNoContent();
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /**
     * F11 (Plan 1, Step 6), proven end-to-end through the real
     * request/service chain (`DestroyFolderRequest`'s 'delete' strategy),
     * not just the repository/service in isolation: a member destroying
     * their own folder must never destroy another user's shared list.
     */
    public function test_deleting_with_delete_strategy_never_destroys_a_shared_list_the_actor_does_not_own(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedList = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($sharedList)->create();
        $memberFolder = Folder::factory()->for($member)->create();
        TaskListMember::factory()->forTaskList($sharedList, $member)->create(['folder_id' => $memberFolder->id]);

        $response = $this->actingAs($member)->deleteJson("/api/v1/folders/{$memberFolder->id}?lists=delete");

        $response->assertNoContent();
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

    public function test_a_user_cannot_access_another_users_folder(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();

        $this->actingAs($stranger)->getJson("/api/v1/folders/{$folder->id}")->assertForbidden();
        $this->actingAs($stranger)->putJson("/api/v1/folders/{$folder->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/v1/folders/{$folder->id}")->assertForbidden();
    }

    public function test_put_folders_order_rewrites_positions(): void
    {
        $user = User::factory()->create();
        $a = Folder::factory()->for($user)->create(['position' => 0]);
        $b = Folder::factory()->for($user)->create(['position' => 1]);

        $response = $this->actingAs($user)->putJson('/api/v1/folders/order', [
            'folder_ids' => [$b->id, $a->id],
        ]);

        $response->assertNoContent();
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_put_folders_order_rejects_a_foreign_id(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create(['position' => 0]);
        $foreign = Folder::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/folders/order', [
            'folder_ids' => [$foreign->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, $foreign->fresh()->position);
    }

    public function test_put_folders_order_rejects_a_stale_incomplete_set(): void
    {
        $user = User::factory()->create();
        $a = Folder::factory()->for($user)->create(['position' => 0]);
        $b = Folder::factory()->for($user)->create(['position' => 1]);

        $response = $this->actingAs($user)->putJson('/api/v1/folders/order', [
            'folder_ids' => [$a->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'folder_reorder_mismatch');
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }
}
