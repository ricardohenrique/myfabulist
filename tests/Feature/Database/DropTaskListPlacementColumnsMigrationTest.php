<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 2: the contract half of
 * expand -> migrate -> contract. Proves `task_lists.folder_id`/`position`
 * and the composite index over them are gone from the live schema, and that
 * the migration's `down()` is schema-reversible (restores the columns; it
 * cannot restore the data — see the migration's own docblock).
 */
class DropTaskListPlacementColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = 'database/migrations/2026_08_12_140000_drop_task_list_placement_columns.php';

    public function test_the_placement_columns_are_gone_from_the_live_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('task_lists', 'folder_id'));
        $this->assertFalse(Schema::hasColumn('task_lists', 'position'));
    }

    public function test_a_task_list_row_carries_no_placement_columns(): void
    {
        $list = TaskList::factory()->create();

        $this->assertArrayNotHasKey('folder_id', $list->fresh()->getAttributes());
        $this->assertArrayNotHasKey('position', $list->fresh()->getAttributes());
    }

    public function test_down_restores_the_dropped_columns(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertTrue(Schema::hasColumn('task_lists', 'folder_id'));
        $this->assertTrue(Schema::hasColumn('task_lists', 'position'));

        // Re-migrate so the rest of the suite (and this test's own
        // RefreshDatabase teardown) sees the real, current schema again.
        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasColumn('task_lists', 'folder_id'));
    }
}
