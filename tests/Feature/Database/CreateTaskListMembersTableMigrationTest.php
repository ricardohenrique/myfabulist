<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Folder;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 1: proves the
 * `create_task_list_members_table` migration's backfill against real,
 * pre-existing `task_lists` rows rather than an empty schema. This is the
 * migration-backfill half of Step 1's acceptance criteria — the complementary
 * steady-state half (every list created after this migration, whether via
 * the repository's dual-write or `TaskList::factory()`, keeps exactly one
 * matching membership row) is covered by `DemoSeederTest` and
 * `TaskListMembershipDualWriteTest`, not here.
 *
 * Deliberately does **not** use RefreshDatabase (mirrors
 * `SeedDemoDatabaseCommandTest`, R1 in that file's own docblock): a real
 * `migrate:fresh`/`migrate:rollback` performs DDL, which implicitly commits
 * in SQLite and would break RefreshDatabase's transaction wrapper around the
 * shared in-memory connection. RefreshDatabase also migrates the schema
 * exactly once, before any test ever runs — by the time a RefreshDatabase
 * test seeds data, this migration's backfill has already executed against
 * an empty `task_lists` table, so it cannot exercise the backfill against
 * real data.
 *
 * The "pre-existing rows" are inserted directly against `task_lists` via
 * `DB::table(...)->insert(...)`, deliberately bypassing
 * `TaskList::factory()` — since `TaskListFactory::configure()` now dual-
 * writes a membership row itself, using the factory here would try to
 * insert into `task_list_members` while it does not exist (mid-rollback),
 * and would also default the scenario away from what this migration
 * actually needs to prove: a list that has never had any membership-aware
 * code touch it, exactly like genuinely pre-existing production data.
 */
class CreateTaskListMembersTableMigrationTest extends TestCase
{
    private const LEGACY_LIST_COUNT = 240;

    private const MIGRATION_PATH = 'database/migrations/2026_08_12_130000_create_task_list_members_table.php';

    /**
     * Plan 1, Step 2 dropped task_lists.folder_id/position in a later
     * migration. To exercise Step 1's backfill migration in the schema
     * state it actually ran against (task_lists still carrying those
     * columns, task_list_members not existing yet), this test also rolls
     * back Step 2's migration before inserting legacy rows, then leaves it
     * rolled back — tearDown()'s migrate:fresh restores the real, current
     * schema for every other test.
     */
    private const DROP_PLACEMENT_COLUMNS_MIGRATION_PATH = 'database/migrations/2026_08_12_140000_drop_task_list_placement_columns.php';

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

    public function test_the_backfill_gives_every_pre_existing_list_exactly_one_accepted_owner_membership(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::DROP_PLACEMENT_COLUMNS_MIGRATION_PATH, '--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasTable('task_list_members'), 'the rollback should have dropped the pivot table.');
        $this->assertTrue(Schema::hasColumn('task_lists', 'folder_id'), 'the rollback should have restored folder_id.');

        $taskListsBeforeMigration = $this->insertLegacyTaskLists(self::LEGACY_LIST_COUNT);

        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertTrue(Schema::hasTable('task_list_members'));
        $this->assertSame(self::LEGACY_LIST_COUNT, TaskListMember::query()->count());

        foreach ($taskListsBeforeMigration as $taskList) {
            $member = TaskListMember::query()->where('task_list_id', $taskList['id'])->sole();

            $this->assertSame($taskList['user_id'], $member->user_id);
            $this->assertSame('accepted', $member->status);
            $this->assertSame($taskList['folder_id'], $member->folder_id);
            $this->assertSame($taskList['position'], $member->position);
            $this->assertNull($member->invited_by_user_id, 'an owner was never invited.');
            $this->assertNull($member->invited_at, 'an owner was never invited.');
            $this->assertNotNull($member->responded_at);
        }
    }

    public function test_down_drops_the_table_cleanly(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('task_list_members'));

        Artisan::call('migrate:rollback', ['--path' => self::DROP_PLACEMENT_COLUMNS_MIGRATION_PATH, '--force' => true]);
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasTable('task_list_members'));
    }

    /**
     * Inserts $count `task_lists` rows directly, spread across a handful of
     * real users/folders, with no membership-aware code involved — the
     * "pre-existing data from before this feature shipped" shape the
     * migration's backfill must repair. $count comfortably exceeds a single
     * SQLite statement's practical row count for this table's column count,
     * proving the single `insertUsing` statement handles realistic volume.
     *
     * @return array<int, array{id: int, user_id: int, folder_id: int|null, position: int}>
     */
    private function insertLegacyTaskLists(int $count): array
    {
        $users = User::factory()->count(5)->create();
        $folders = Folder::factory()->count(5)->create();
        $now = now();

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            // Every third list is ungrouped, to exercise a null folder_id too.
            $folder = $i % 3 === 0 ? null : $folders->random();

            $rows[] = [
                'user_id' => $user->id,
                'folder_id' => $folder?->id,
                'name' => "Legacy list {$i}",
                'is_default' => false,
                'position' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Inserted in batches — a single 240-row x 8-column statement risks
        // the same bound-parameter ceiling the production migration itself
        // now avoids by using insertUsing() instead of chunked PHP inserts.
        foreach (array_chunk($rows, 50) as $batch) {
            DB::table('task_lists')->insert($batch);
        }

        return DB::table('task_lists')
            ->orderBy('id')
            ->get(['id', 'user_id', 'folder_id', 'position'])
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
