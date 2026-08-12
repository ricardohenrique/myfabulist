<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\FolderNotEmptyException;
use App\Models\Folder;
use App\Models\TaskList;
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
