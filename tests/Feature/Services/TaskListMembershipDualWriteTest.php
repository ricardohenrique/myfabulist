<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\TaskListMember;
use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"): every list creation path must
 * write exactly one accepted owner membership row alongside the
 * `task_lists` row it creates. This started as Step 1's temporary
 * dual-write and became the sole placement write in Step 2, once
 * `task_lists.folder_id`/`position` were dropped — this test's assertions
 * are unchanged across that cutover, since a list's `position` is read off
 * whatever `EloquentTaskListRepository::create()`/`createDefaultFor()`
 * hands back either way.
 */
class TaskListMembershipDualWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_list_via_the_service_creates_exactly_one_owner_membership(): void
    {
        $user = User::factory()->create();
        $service = app(TaskListService::class);

        $list = $service->create($user, 'Groceries', null);

        $members = TaskListMember::query()->where('task_list_id', $list->id)->get();

        $this->assertCount(1, $members);
        $member = $members->first();
        $this->assertSame($user->id, $member->user_id);
        $this->assertSame('accepted', $member->status);
        $this->assertNull($member->folder_id);
        $this->assertSame($list->position, $member->position);
    }

    public function test_creating_the_inbox_via_the_service_creates_exactly_one_owner_membership(): void
    {
        $user = User::factory()->create();
        $service = app(TaskListService::class);

        $inbox = $service->inboxFor($user);

        $members = TaskListMember::query()->where('task_list_id', $inbox->id)->get();

        $this->assertCount(1, $members);
        $member = $members->first();
        $this->assertSame($user->id, $member->user_id);
        $this->assertSame('accepted', $member->status);
        $this->assertNull($member->folder_id);
        $this->assertSame(0, $member->position);
    }

    public function test_calling_inbox_for_a_second_time_does_not_create_a_second_membership_row(): void
    {
        $user = User::factory()->create();
        $service = app(TaskListService::class);

        $first = $service->inboxFor($user);
        $service->inboxFor($user);

        $this->assertSame(1, TaskListMember::query()->where('task_list_id', $first->id)->count());
    }
}
