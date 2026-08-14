<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 3: proves the
 * `create_task_stars_table` migration's backfill against real, pre-existing
 * `tasks` rows rather than an empty schema — mirrors
 * `CreateTaskListMembersTableMigrationTest`'s approach for Step 1.
 *
 * Deliberately does **not** use RefreshDatabase, for the same reason as
 * that test: a real `migrate:fresh`/`migrate:rollback` performs DDL, which
 * implicitly commits in SQLite and would break RefreshDatabase's
 * transaction wrapper around the shared in-memory connection.
 *
 * The "pre-existing rows" are inserted directly against `tasks` via
 * `DB::table(...)->insert(...)`, bypassing `Task::factory()` — the factory's
 * `starred()` state writes a `task_stars` row itself (Plan 1, Step 3), which
 * would try to insert into a table that does not exist yet at the point
 * this migration is rolled back to, and would also default the scenario
 * away from what this migration actually needs to prove: `is_starred = true`
 * rows that have never had any star-pivot-aware code touch them, exactly
 * like genuinely pre-existing production data.
 */
class CreateTaskStarsTableMigrationTest extends TestCase
{
    private const LEGACY_TASK_COUNT = 120;

    private const MIGRATION_PATH = 'database/migrations/2026_08_12_150000_create_task_stars_table.php';

    private const DROP_IS_STARRED_MIGRATION_PATH = 'database/migrations/2026_08_12_160000_drop_is_starred_from_tasks_table.php';

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

    public function test_the_backfill_gives_every_pre_existing_starred_task_exactly_one_star_row_for_its_creator(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::DROP_IS_STARRED_MIGRATION_PATH, '--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasTable('task_stars'), 'the rollback should have dropped the pivot table.');
        $this->assertTrue(Schema::hasColumn('tasks', 'is_starred'), 'the rollback should have restored is_starred.');

        $tasksBeforeMigration = $this->insertLegacyTasks(self::LEGACY_TASK_COUNT);
        $starredTasks = array_values(array_filter($tasksBeforeMigration, fn (array $task): bool => $task['is_starred']));

        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertTrue(Schema::hasTable('task_stars'));
        $this->assertSame(count($starredTasks), DB::table('task_stars')->count());

        foreach ($starredTasks as $task) {
            $star = DB::table('task_stars')->where('task_id', $task['id'])->sole();

            $this->assertSame($task['user_id'], $star->user_id);
        }

        // Every non-starred task must have produced no row at all.
        $notStarredIds = array_column(
            array_filter($tasksBeforeMigration, fn (array $task): bool => ! $task['is_starred']),
            'id',
        );
        $this->assertSame(0, DB::table('task_stars')->whereIn('task_id', $notStarredIds)->count());
    }

    public function test_down_drops_the_table_cleanly(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('task_stars'));

        Artisan::call('migrate:rollback', ['--path' => self::DROP_IS_STARRED_MIGRATION_PATH, '--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasTable('task_stars'));
    }

    /**
     * Inserts $count `tasks` rows directly, roughly a third of them
     * starred, spread across a handful of real users/lists, with no
     * star-pivot-aware code involved.
     *
     * @return array<int, array{id: int, user_id: int, is_starred: bool}>
     */
    private function insertLegacyTasks(int $count): array
    {
        $users = User::factory()->count(5)->create();
        $now = now();

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $isStarred = $i % 3 === 0;

            $rows[] = [
                'user_id' => $user->id,
                'task_list_id' => DB::table('task_lists')->insertGetId([
                    'user_id' => $user->id,
                    'name' => "Legacy list {$i}",
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
                'title' => "Legacy task {$i}",
                'is_completed' => false,
                'is_starred' => $isStarred,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $batch) {
            DB::table('tasks')->insert($batch);
        }

        return DB::table('tasks')
            ->orderBy('id')
            ->get(['id', 'user_id', 'is_starred'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'is_starred' => (bool) $row->is_starred,
            ])
            ->all();
    }
}
