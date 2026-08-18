<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnboardingMigrationTest extends TestCase
{
    private const MIGRATION_PATH = 'database/migrations/2026_08_18_100000_add_onboarding_to_users_table.php';

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

    public function test_existing_accounts_are_not_prompted_after_the_migration_is_deployed(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertFalse(Schema::hasColumn('users', 'onboarding_completed_at'));

        $userId = DB::table('users')->insertGetId([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $user = DB::table('users')->find($userId);

        $this->assertNull($user->onboarding_use_case);
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_accounts_created_after_the_migration_start_with_pending_onboarding(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->find($userId);

        $this->assertNull($user->onboarding_use_case);
        $this->assertNull($user->onboarding_completed_at);
    }
}
