<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateNotificationsTableMigrationTest extends TestCase
{
    private const MIGRATION_PATH = 'database/migrations/2026_08_19_120000_create_notifications_table.php';

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

    public function test_it_backfills_existing_pending_invitations_as_unread_notifications(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION_PATH, '--force' => true]);
        $this->assertFalse(Schema::hasTable('notifications'));

        $owner = User::factory()->create(['name' => 'Existing owner']);
        $invitee = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Existing share']);
        $invitation = TaskListMember::factory()
            ->forTaskList($list, $invitee)
            ->pending()
            ->create([
                'invited_by_user_id' => $owner->id,
                'invited_at' => now()->subDay(),
            ]);

        Artisan::call('migrate', ['--path' => self::MIGRATION_PATH, '--force' => true]);

        $this->assertTrue(Schema::hasTable('notifications'));
        $notification = DB::table('notifications')->sole();
        $data = json_decode($notification->data, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($invitee->id, $notification->notifiable_id);
        $this->assertNull($notification->read_at);
        $this->assertSame('list_invitation', $data['kind']);
        $this->assertSame($invitation->id, $data['membership_id']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame($list->id, $data['list']['id']);
        $this->assertSame($owner->id, $data['actor']['id']);
    }
}
