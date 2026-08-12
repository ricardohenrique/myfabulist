<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Exceptions\TaskListMemberLimitReachedException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskListMemberRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskListMemberRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TaskListMemberRepositoryInterface::class);
    }

    public function test_find_membership_returns_the_matching_row(): void
    {
        $owner = User::factory()->create();
        // TaskListFactory::configure() already dual-writes the owner's own
        // accepted membership row — that row is the one under test here.
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = TaskListMember::query()->where('task_list_id', $list->id)->sole();

        $found = $this->repository->findMembership($list, $owner);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($member));
    }

    public function test_find_membership_returns_null_when_no_row_exists(): void
    {
        $list = TaskList::factory()->create();
        $stranger = User::factory()->create();

        $this->assertNull($this->repository->findMembership($list, $stranger));
    }

    public function test_accepted_members_for_returns_only_accepted_rows_with_user_loaded(): void
    {
        // TaskListFactory::configure() already dual-writes one accepted
        // owner membership; the other two prove the status filter.
        $list = TaskList::factory()->create();
        $accepted = TaskListMember::query()->where('task_list_id', $list->id)->sole();
        TaskListMember::factory()->forTaskList($list, User::factory()->create())->pending()->create();
        TaskListMember::factory()->forTaskList($list, User::factory()->create())->create(['status' => 'declined']);

        $members = $this->repository->acceptedMembersFor($list);

        $this->assertSame([$accepted->id], $members->pluck('id')->all());
        $this->assertTrue($members->first()->relationLoaded('user'));
    }

    public function test_pending_for_returns_only_this_users_pending_invitations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $inviter = User::factory()->create();
        $list = TaskList::factory()->create();
        $anotherList = TaskList::factory()->create();

        $pending = TaskListMember::factory()
            ->forTaskList($list, $user)
            ->pending()
            ->create(['invited_by_user_id' => $inviter->id]);
        // Same user, but already accepted elsewhere — excluded by status.
        TaskListMember::factory()->forTaskList($anotherList, $user)->create(['status' => 'accepted']);
        // A different user's pending invitation on the same list — excluded by user.
        TaskListMember::factory()->forTaskList($list, $otherUser)->pending()->create();

        $invitations = $this->repository->pendingFor($user);

        $this->assertSame([$pending->id], $invitations->pluck('id')->all());
        $this->assertTrue($invitations->first()->relationLoaded('taskList'));
        $this->assertTrue($invitations->first()->relationLoaded('invitedBy'));
    }

    public function test_pending_count_for_counts_only_pending_rows(): void
    {
        $user = User::factory()->create();
        TaskListMember::factory()->pending()->create(['user_id' => $user->id]);
        TaskListMember::factory()->pending()->create(['user_id' => $user->id]);
        TaskListMember::factory()->create(['user_id' => $user->id, 'status' => 'accepted']);

        $this->assertSame(2, $this->repository->pendingCountFor($user));
    }

    public function test_create_owner_membership_writes_the_given_placement(): void
    {
        $owner = User::factory()->create();
        // Built directly (not via TaskList::factory(), which already
        // creates an owner membership) — this test needs a list with no
        // membership row yet, exactly like EloquentTaskListRepository's own
        // pre-transaction state before it calls createOwnerMembership().
        $list = new TaskList;
        $list->forceFill([
            'user_id' => $owner->id,
            'name' => 'Groceries',
            'is_default' => false,
        ])->save();

        $member = $this->repository->createOwnerMembership($list, null, 3);

        $this->assertSame($list->id, $member->task_list_id);
        $this->assertSame($owner->id, $member->user_id);
        $this->assertSame('accepted', $member->status);
        $this->assertNull($member->folder_id);
        $this->assertSame(3, $member->position);
        $this->assertNull($member->invited_by_user_id);
        $this->assertNotNull($member->responded_at);
    }

    public function test_create_makes_a_pending_invitation_attributed_to_the_inviter(): void
    {
        $list = TaskList::factory()->create();
        $invitee = User::factory()->create();
        $inviter = User::factory()->create();

        $member = $this->repository->create($list, $invitee, $inviter);

        $this->assertSame('pending', $member->status);
        $this->assertSame($invitee->id, $member->user_id);
        $this->assertSame($inviter->id, $member->invited_by_user_id);
        $this->assertNotNull($member->invited_at);
        $this->assertNull($member->responded_at);
    }

    public function test_create_is_idempotent_for_an_existing_pending_invitation(): void
    {
        $list = TaskList::factory()->create();
        $invitee = User::factory()->create();
        $firstInviter = User::factory()->create();
        $secondInviter = User::factory()->create();

        $first = $this->repository->create($list, $invitee, $firstInviter);
        $second = $this->repository->create($list, $invitee, $secondInviter);

        $this->assertTrue($first->is($second));
        $this->assertSame($firstInviter->id, $second->invited_by_user_id);
        $this->assertSame(1, TaskListMember::query()->where('task_list_id', $list->id)->where('user_id', $invitee->id)->count());
    }

    public function test_create_reactivates_a_declined_row_back_to_pending_on_the_same_row(): void
    {
        $list = TaskList::factory()->create();
        $invitee = User::factory()->create();
        $originalInviter = User::factory()->create();
        $reInviter = User::factory()->create();

        $declined = TaskListMember::factory()
            ->forTaskList($list, $invitee)
            ->create([
                'status' => 'declined',
                'invited_by_user_id' => $originalInviter->id,
                'invited_at' => now()->subDay(),
                'responded_at' => now()->subHour(),
            ]);

        $reInvited = $this->repository->create($list, $invitee, $reInviter);

        $this->assertSame($declined->id, $reInvited->id);
        $this->assertSame('pending', $reInvited->status);
        $this->assertSame($reInviter->id, $reInvited->invited_by_user_id);
        $this->assertNull($reInvited->responded_at);
        $this->assertTrue($reInvited->invited_at->greaterThan($declined->invited_at));
        $this->assertSame(1, TaskListMember::query()->where('task_list_id', $list->id)->where('user_id', $invitee->id)->count());
    }

    public function test_accept_invitation_flips_status_places_ungrouped_and_stamps_responded_at(): void
    {
        $list = TaskList::factory()->create();
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();

        $accepted = $this->repository->acceptInvitation($pending, 3, 10);

        $this->assertSame('accepted', $accepted->status);
        $this->assertNull($accepted->folder_id);
        $this->assertSame(3, $accepted->position);
        $this->assertNotNull($accepted->responded_at);
        $this->assertSame('accepted', $pending->fresh()->status);
    }

    public function test_accept_invitation_throws_and_writes_nothing_when_the_locked_count_is_at_the_cap(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();
        $pending = TaskListMember::factory()->forTaskList($list, $member)->pending()->create();
        // Owner's own accepted row already makes the locked count 1.

        try {
            $this->repository->acceptInvitation($pending, 0, 1);
            $this->fail('Expected TaskListMemberLimitReachedException to be thrown.');
        } catch (TaskListMemberLimitReachedException) {
            // expected
        }

        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_update_status_persists_the_new_status_and_stamps_responded_at(): void
    {
        $member = TaskListMember::factory()->pending()->create();
        $this->assertNull($member->responded_at);

        $updated = $this->repository->updateStatus($member, 'declined');

        $this->assertSame('declined', $updated->status);
        $this->assertNotNull($updated->responded_at);
        $this->assertSame('declined', $member->fresh()->status);
        $this->assertNotNull($member->fresh()->responded_at);
    }

    public function test_update_placement_persists_folder_and_position(): void
    {
        $member = TaskListMember::factory()->create(['folder_id' => null, 'position' => 0]);
        $folder = Folder::factory()->create();

        $this->repository->updatePlacement($member, $folder->id, 2);

        $this->assertSame($folder->id, $member->fresh()->folder_id);
        $this->assertSame(2, $member->fresh()->position);
    }

    public function test_delete_removes_the_row(): void
    {
        $member = TaskListMember::factory()->create();

        $this->repository->delete($member);

        $this->assertDatabaseMissing('task_list_members', ['id' => $member->id]);
    }

    public function test_next_position_for_on_an_empty_scope_is_zero(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->nextPositionFor($user, null));
    }

    public function test_next_position_for_is_scoped_per_user_and_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        TaskListMember::factory()->create(['user_id' => $user->id, 'folder_id' => null, 'position' => 3, 'status' => 'accepted']);
        TaskListMember::factory()->create(['user_id' => $user->id, 'folder_id' => $folder->id, 'position' => 1, 'status' => 'accepted']);

        $this->assertSame(4, $this->repository->nextPositionFor($user, null));
        $this->assertSame(2, $this->repository->nextPositionFor($user, $folder->id));
    }

    /**
     * A pending invite's placeholder position=0 must never be mistaken for
     * a real accepted slot.
     */
    public function test_next_position_for_ignores_pending_memberships(): void
    {
        $user = User::factory()->create();
        TaskListMember::factory()->pending()->create(['user_id' => $user->id, 'folder_id' => null, 'position' => 5]);

        $this->assertSame(0, $this->repository->nextPositionFor($user, null));
    }

    /**
     * Mirrors EloquentTaskListRepository::nextPosition() getting soft-delete
     * exclusion for free from TaskList::query()'s global scope.
     */
    public function test_next_position_for_ignores_memberships_of_a_soft_deleted_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create();
        TaskListMember::factory()->forTaskList($list, $user)->create(['folder_id' => null, 'position' => 5]);
        $list->delete();

        $this->assertSame(0, $this->repository->nextPositionFor($user, null));
    }

    public function test_count_accepted_for_counts_only_accepted_members(): void
    {
        // TaskListFactory::configure() already dual-writes one accepted
        // owner membership; the extra rows below use distinct users so they
        // don't collide with it under the unique (task_list_id, user_id)
        // constraint.
        $list = TaskList::factory()->create();
        TaskListMember::factory()->forTaskList($list, User::factory()->create())->create(['status' => 'accepted']);
        TaskListMember::factory()->forTaskList($list, User::factory()->create())->pending()->create();

        $this->assertSame(2, $this->repository->countAcceptedFor($list));
    }
}
