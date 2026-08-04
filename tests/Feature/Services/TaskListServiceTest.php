<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskListService;
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
}
