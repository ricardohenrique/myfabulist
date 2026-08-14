<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Deliberately does **not** use RefreshDatabase (R1): a real `migrate:fresh`
 * performs DDL, which implicitly commits in SQLite and would break
 * RefreshDatabase's transaction wrapper around the shared in-memory
 * connection, leaking seeded rows into whichever test runs next. Every test
 * method here restores a clean, migrated connection in `tearDown()`
 * regardless of whether it actually reached `migrate:fresh`.
 */
class SeedDemoDatabaseCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Guarantees a schema exists even if this class happens to run
        // before any RefreshDatabase test has migrated the shared
        // connection; a safe no-op otherwise.
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        // Hands the shared in-memory connection back empty-but-migrated no
        // matter what this test did, so no later test — RefreshDatabase or
        // not — inherits leftover rows (R1).
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_it_refuses_to_run_in_production_and_leaves_the_database_untouched(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $marker = User::factory()->create();

        $this->artisan('db:fresh-seed')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        $this->assertDatabaseHas('users', ['id' => $marker->id]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_it_refuses_to_run_in_staging_and_leaves_the_database_untouched(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');

        $marker = User::factory()->create();

        $this->artisan('db:fresh-seed')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        $this->assertDatabaseHas('users', ['id' => $marker->id]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_force_flag_is_declared_on_the_signature(): void
    {
        $definition = Artisan::all()['db:fresh-seed']->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
    }

    public function test_force_bypasses_the_guard_and_seeds_the_full_demo_dataset(): void
    {
        $this->app->detectEnvironment(fn (): string => 'testing');

        $this->artisan('db:fresh-seed --force')->assertSuccessful();

        $this->assertSame(20, User::query()->count());
        $this->assertGreaterThan(0, Task::query()->count());

        User::query()->get()->each(function (User $user): void {
            $this->assertDatabaseHas('task_lists', [
                'user_id' => $user->id,
                'is_default' => true,
            ]);
            $this->assertDatabaseHas('task_list_members', [
                'user_id' => $user->id,
                'folder_id' => null,
                'position' => 0,
            ]);
        });
    }
}
