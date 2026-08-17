<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_small_acceptance_fixture_with_member_scoped_list_placement(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $workFolder = Folder::query()
            ->where('user_id', $user->id)
            ->where('name', 'Work')
            ->firstOrFail();
        $websiteLaunch = TaskList::query()
            ->where('user_id', $user->id)
            ->where('name', 'Website launch')
            ->firstOrFail();
        $groceries = TaskList::query()
            ->where('user_id', $user->id)
            ->where('name', 'Groceries')
            ->firstOrFail();

        $this->assertSame(3, TaskList::query()->where('user_id', $user->id)->count());
        $this->assertSame(5, Task::query()->where('task_list_id', $websiteLaunch->id)->count());

        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $websiteLaunch->id,
            'user_id' => $user->id,
            'folder_id' => $workFolder->id,
            'position' => 0,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('task_list_members', [
            'task_list_id' => $groceries->id,
            'user_id' => $user->id,
            'folder_id' => null,
            'position' => 1,
            'status' => 'accepted',
        ]);
    }

    public function test_it_seeds_the_workspace_background_option_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, WorkspaceBackgroundOption::query()->count());
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'flat_color_amethyst', 'type' => 'flat_color', 'enabled' => true]);
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'flat_color_terracotta', 'type' => 'flat_color', 'enabled' => true]);
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'gradient_meadow', 'type' => 'gradient', 'enabled' => true]);
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'gradient_twilight', 'type' => 'gradient', 'enabled' => true]);
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'image_aurora_waves', 'type' => 'image', 'enabled' => true]);
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'image_dune_drift', 'type' => 'image', 'enabled' => true]);

        // Exactly one catalog row is the platform default — Twilight,
        // matching the brand identity (WorkspaceBackgroundService::
        // assignDefaultTo(), "Use default" in the picker).
        $this->assertSame(1, WorkspaceBackgroundOption::query()->where('is_default', true)->count());
        $this->assertDatabaseHas('workspace_background_options', ['key' => 'gradient_twilight', 'is_default' => true]);
    }
}
