<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 1: the schema-level
 * guarantees the sharing lifecycle (Steps 5-6) will depend on — the unique
 * membership pair (duplicate-invite prevention), `folder_id` nulling on
 * folder deletion (F11), and cascade deletes on both `task_list_id` and
 * `user_id`.
 */
class TaskListMembersTableConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_have_two_membership_rows_for_the_same_list(): void
    {
        $list = TaskList::factory()->create();
        $user = User::factory()->create();
        TaskListMember::factory()->forTaskList($list, $user)->create();

        $this->expectException(QueryException::class);

        TaskListMember::factory()->forTaskList($list, $user)->create();
    }

    public function test_deleting_a_folder_nulls_the_memberships_placement(): void
    {
        $folder = Folder::factory()->create();
        $member = TaskListMember::factory()->create(['folder_id' => $folder->id]);

        $folder->delete();

        $this->assertNull($member->fresh()->folder_id);
    }

    public function test_force_deleting_a_list_cascades_to_its_membership_rows(): void
    {
        $list = TaskList::factory()->create();
        TaskListMember::factory()->forTaskList($list, User::factory()->create())->create();

        $this->assertGreaterThan(0, TaskListMember::query()->where('task_list_id', $list->id)->count());

        $list->forceDelete();

        $this->assertSame(0, TaskListMember::query()->where('task_list_id', $list->id)->count());
    }

    public function test_deleting_a_user_cascades_to_their_membership_rows(): void
    {
        $user = User::factory()->create();
        $member = TaskListMember::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('task_list_members', ['id' => $member->id]);
    }
}
