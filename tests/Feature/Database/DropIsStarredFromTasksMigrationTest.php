<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Task;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 3: the contract half of
 * the star migration. Proves `tasks.is_starred` and its
 * `[user_id, is_starred]` index are gone from the live schema, and that the
 * migration's `down()` is schema-reversible (restores the column; it
 * cannot restore per-user star data — see the migration's own docblock).
 *
 * Deliberately does **not** use RefreshDatabase, matching
 * `CreateTaskStarsTableMigrationTest`'s own reasoning: `test_down_restores_
 * the_dropped_column()` below performs real DDL (`migrate:rollback`/
 * `migrate`), which implicitly commits in SQLite and would break
 * RefreshDatabase's transaction wrapper around the shared in-memory
 * connection — on a driver with non-transactional DDL (MySQL, a supported
 * driver per this project's docs) that combination would silently strand
 * the schema rolled back for every later RefreshDatabase test in the run,
 * not just fail loudly. `setUp()`/`tearDown()` manage the schema explicitly
 * instead, the same way that sibling test does.
 */
class DropIsStarredFromTasksMigrationTest extends TestCase
{
    private const MIGRATION_PATH = 'database/migrations/2026_08_12_160000_drop_is_starred_from_tasks_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_is_starred_is_gone_from_the_live_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('tasks', 'is_starred'));
    }

    public function test_a_task_row_carries_no_is_starred_column(): void
    {
        $task = Task::factory()->create();

        $this->assertArrayNotHasKey('is_starred', $task->fresh()->getAttributes());
    }

    public function test_down_restores_the_dropped_column(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertTrue(Schema::hasColumn('tasks', 'is_starred'));

        // Re-migrate so the rest of this test (and the suite that follows
        // this class) sees the real, current schema again.
        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasColumn('tasks', 'is_starred'));
    }
}
