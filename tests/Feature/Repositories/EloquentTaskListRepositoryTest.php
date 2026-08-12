<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Exceptions\TaskListNotFoundException;
use App\Exceptions\TaskListReorderMismatchException;
use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\TaskStar;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskListRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskListRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TaskListRepositoryInterface::class);
    }

    public function test_all_for_user_returns_only_that_users_lists_ordered_by_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $second = TaskList::factory()->atPosition(1)->create(['user_id' => $user->id]);
        $first = TaskList::factory()->atPosition(0)->create(['user_id' => $user->id]);
        TaskList::factory()->create(['user_id' => $other->id]);

        $lists = $this->repository->allForUser($user);

        $this->assertSame([$first->id, $second->id], $lists->pluck('id')->all());
    }

    public function test_all_for_user_returns_an_empty_collection_when_the_user_has_no_lists(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->repository->allForUser($user)->isEmpty());
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 2/4: allForUser()
     * joins task_list_members and aliases the *viewing* user's own
     * folder_id/position onto each TaskList row. Built directly via
     * TaskListMember::factory() (bypassing the normal single-owner
     * create()/factory flow, since no invite UI exists yet) to prove two
     * members of the same list each see their own placement, independent
     * of the other's — asserted both at the membership level and (since
     * Step 4 widened allForUser() past ownership) through
     * allForUser($collaborator) itself.
     */
    public function test_two_members_of_the_same_list_have_independent_placements(): void
    {
        $owner = User::factory()->create();
        $ownerFolder = Folder::factory()->for($owner)->create();
        $list = TaskList::factory()->inFolder($ownerFolder, 4)->create(['user_id' => $owner->id]);

        $collaborator = User::factory()->create();
        $collaboratorFolder = Folder::factory()->for($collaborator)->create();
        TaskListMember::factory()
            ->forTaskList($list, $collaborator)
            ->create(['folder_id' => $collaboratorFolder->id, 'position' => 7]);

        $ownerView = $this->repository->allForUser($owner)->firstOrFail();
        $this->assertSame($ownerFolder->id, $ownerView->folder_id);
        $this->assertSame(4, $ownerView->position);

        $collaboratorView = $this->repository->allForUser($collaborator)->firstOrFail();
        $this->assertSame($collaboratorFolder->id, $collaboratorView->folder_id);
        $this->assertSame(7, $collaboratorView->position);

        $collaboratorMembership = TaskListMember::query()
            ->where('task_list_id', $list->id)
            ->where('user_id', $collaborator->id)
            ->sole();
        $this->assertSame($collaboratorFolder->id, $collaboratorMembership->folder_id);
        $this->assertSame(7, $collaboratorMembership->position);

        // The owner's own row is untouched by the collaborator's placement.
        $ownerMembership = TaskListMember::query()
            ->where('task_list_id', $list->id)
            ->where('user_id', $owner->id)
            ->sole();
        $this->assertSame($ownerFolder->id, $ownerMembership->folder_id);
        $this->assertSame(4, $ownerMembership->position);
    }

    public function test_find_accessible_for_returns_null_for_a_non_member(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $this->assertNull($this->repository->findAccessibleFor($list->id, $stranger));
        $this->assertNotNull($this->repository->findAccessibleFor($list->id, $owner));
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4: findAccessibleFor()
     * is scoped to *any* accepted membership, not ownership — a non-owner
     * member can resolve a list they were invited to and accepted,
     * constructed directly via the factory since no invite UI exists yet.
     */
    public function test_find_accessible_for_returns_the_list_for_an_accepted_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $found = $this->repository->findAccessibleFor($list->id, $member);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($list));
    }

    public function test_find_accessible_for_returns_null_for_a_pending_or_declined_member(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $declined = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();
        TaskListMember::factory()->forTaskList($list, $declined)->create(['status' => 'declined']);

        $this->assertNull($this->repository->findAccessibleFor($list->id, $pending));
        $this->assertNull($this->repository->findAccessibleFor($list->id, $declined));
    }

    /**
     * Regression: findAccessibleFor() (and findDefaultFor()/
     * findDeletedForUser(), covered below) must carry the viewer's own
     * placement exactly like allForUser()'s join does — a plain, unjoined
     * lookup would silently hand back a list with a null position/folder_id
     * (Plan 1, Step 2 review follow-up).
     */
    public function test_find_accessible_for_carries_the_viewers_own_placement(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();
        $list = TaskList::factory()->inFolder($folder, 3)->create(['user_id' => $owner->id]);

        $found = $this->repository->findAccessibleFor($list->id, $owner);

        $this->assertNotNull($found);
        $this->assertSame($folder->id, $found->folder_id);
        $this->assertSame(3, $found->position);
    }

    /**
     * findOwnedBy() — the strictly ownership-scoped sibling used only by
     * TaskService::move() (Plan 1, Step 4/Q8) — must exclude a list the
     * user merely has an accepted membership on.
     */
    public function test_find_owned_by_returns_null_for_an_accepted_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        $this->assertNull($this->repository->findOwnedBy($list->id, $member));
        $this->assertNotNull($this->repository->findOwnedBy($list->id, $owner));
    }

    public function test_find_default_for_carries_the_viewers_own_placement(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $found = $this->repository->findDefaultFor($user);

        $this->assertNotNull($found);
        $this->assertNull($found->folder_id);
        $this->assertSame(0, $found->position);
    }

    /**
     * findForRouteBinding() is deliberately unscoped by ownership (route
     * binding must still resolve a list the viewer can't access, so the
     * policy — not a 404 — is what denies it), but it must still carry the
     * viewer's own placement when they *are* a member, and resolve with a
     * null placement (not a missing list) when they are a stranger.
     */
    public function test_find_for_route_binding_carries_the_viewers_placement_when_a_member(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->for($owner)->create();
        $list = TaskList::factory()->inFolder($folder, 2)->create(['user_id' => $owner->id]);

        $found = $this->repository->findForRouteBinding($list->id, $owner);

        $this->assertNotNull($found);
        $this->assertSame($folder->id, $found->folder_id);
        $this->assertSame(2, $found->position);
    }

    public function test_find_for_route_binding_still_resolves_for_a_non_member_with_a_null_placement(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $found = $this->repository->findForRouteBinding($list->id, $stranger);

        $this->assertNotNull($found);
        $this->assertNull($found->position);
        $this->assertNull($found->folder_id);
    }

    public function test_find_for_route_binding_resolves_without_a_viewer(): void
    {
        $list = TaskList::factory()->create();

        $found = $this->repository->findForRouteBinding($list->id, null);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($list));
    }

    public function test_find_for_route_binding_returns_null_for_a_missing_id(): void
    {
        $this->assertNull($this->repository->findForRouteBinding(999999, User::factory()->create()));
    }

    /**
     * Plan 1, Step 4: allForUser()'s where(task_lists.user_id) filter —
     * deliberately kept by Step 2 pending this step's authorization work —
     * is now removed. allForUser() feeds NavigationService::treeFor() (the
     * sidebar), so a list a user merely accepted an invitation to (not one
     * they own) must appear in their own tree; that is the entire point of
     * accepting an invitation. Constructed directly via the factory,
     * bypassing the not-yet-built invite UI (Step 5).
     */
    public function test_all_for_user_includes_a_list_the_user_has_accepted_membership_on_but_does_not_own(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $collaborator = User::factory()->create();
        TaskListMember::factory()->forTaskList($list, $collaborator)->create();

        $found = $this->repository->allForUser($collaborator);

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($list));
    }

    /**
     * A pending or declined membership must not surface the list in the
     * sidebar — only 'accepted' does.
     */
    public function test_all_for_user_excludes_a_list_with_a_pending_or_declined_membership(): void
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);

        $pending = User::factory()->create();
        $declined = User::factory()->create();
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();
        TaskListMember::factory()->forTaskList($list, $declined)->create(['status' => 'declined']);

        $this->assertTrue($this->repository->allForUser($pending)->isEmpty());
        $this->assertTrue($this->repository->allForUser($declined)->isEmpty());
    }

    public function test_create_default_for_is_the_sole_way_the_inbox_is_provisioned(): void
    {
        $user = User::factory()->create();

        $inbox = $this->repository->createDefaultFor($user);

        $this->assertTrue($inbox->is_default);
        $this->assertNull($inbox->folder_id);
        $this->assertSame('Inbox', $inbox->name);
        $this->assertTrue($inbox->is($this->repository->findDefaultFor($user)));
    }

    public function test_find_default_for_returns_null_when_none_exists(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->repository->findDefaultFor($user));
    }

    public function test_next_position_on_an_empty_scope_is_zero(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->nextPosition($user, null));
    }

    public function test_next_position_is_scoped_per_folder(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        TaskList::factory()->atPosition(3)->create(['user_id' => $user->id]);
        TaskList::factory()->inFolder($folder, 1)->create();

        $this->assertSame(4, $this->repository->nextPosition($user, null));
        $this->assertSame(2, $this->repository->nextPosition($user, $folder->id));
    }

    public function test_update_can_move_a_list_between_folders(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $updated = $this->repository->update($list, $user, 'Renamed', $folder, 0);

        $this->assertSame('Renamed', $list->fresh()->name);
        $this->assertSame($folder->id, $updated->folder_id);
        $this->assertSame($folder->id, $this->membershipFor($list, $user)->folder_id);
    }

    /**
     * Plan 1, Step 4 (code-review follow-up): update() does not trust the
     * caller alone to have already checked membership status —
     * findMembership() deliberately returns a row of *any* status, so the
     * repository itself must require 'accepted' before renaming/
     * repositioning through it. Unreachable via HTTP today
     * (UpdateTaskListRequest already authorizes via the accepted-only
     * `update` ability), but exercised directly here per this project's
     * "repository enforces its own invariants regardless of caller"
     * convention (mirrors TaskService::move()'s findOwnedBy()).
     */
    public function test_update_throws_for_a_pending_or_declined_membership(): void
    {
        $owner = User::factory()->create();
        $pending = User::factory()->create();
        $declined = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id, 'name' => 'Original']);
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();
        TaskListMember::factory()->forTaskList($list, $declined)->create(['status' => 'declined']);

        try {
            $this->repository->update($list, $pending, 'Renamed by pending', null, 0);
            $this->fail('Expected a TaskListNotFoundException for a pending membership.');
        } catch (TaskListNotFoundException $exception) {
            $this->assertSame('task_list_not_found', $exception->errorCode());
        }

        try {
            $this->repository->update($list, $declined, 'Renamed by declined', null, 0);
            $this->fail('Expected a TaskListNotFoundException for a declined membership.');
        } catch (TaskListNotFoundException $exception) {
            $this->assertSame('task_list_not_found', $exception->errorCode());
        }

        $this->assertSame('Original', $list->fresh()->name);
    }

    public function test_all_for_user_carries_both_the_total_and_active_task_counts(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($list)->create();
        Task::factory()->forTaskList($list)->completed()->create();
        Task::factory()->forTaskList($list)->completed()->create();

        $found = $this->repository->allForUser($user)->firstOrFail();

        $this->assertSame(3, $found->tasks_count);
        $this->assertSame(1, $found->active_tasks_count);
    }

    /**
     * Plan 4/Step 2: a deleted task must not inflate either count on the
     * list read used by the sidebar/list index.
     */
    public function test_all_for_user_task_counts_exclude_deleted_tasks(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $kept = Task::factory()->forTaskList($list)->create();
        $deleted = Task::factory()->forTaskList($list)->create();
        $deleted->delete();

        $found = $this->repository->allForUser($user)->firstOrFail();

        $this->assertSame(1, $found->tasks_count);
        $this->assertSame(1, $found->active_tasks_count);
        $this->assertSame($kept->id, $list->fresh()->tasks()->firstOrFail()->id);
    }

    /**
     * Plan 4/Step 5: findDeletedForUser() is the un-delete lookup — it must
     * return the list only when it is both trashed and owned by the caller.
     */
    public function test_find_deleted_for_user_returns_a_trashed_owned_list(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder, 5)->create(['user_id' => $user->id]);
        $this->repository->delete($list);

        $found = $this->repository->findDeletedForUser($list->id, $user);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($list));
        $this->assertSame($folder->id, $found->folder_id);
        $this->assertSame(5, $found->position);
    }

    public function test_find_deleted_for_user_returns_null_for_a_live_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $this->assertNull($this->repository->findDeletedForUser($list->id, $user));
    }

    public function test_find_deleted_for_user_returns_null_for_a_trashed_foreign_list(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $this->repository->delete($list);

        $this->assertNull($this->repository->findDeletedForUser($list->id, $stranger));
    }

    /**
     * Plan 1, Step 4: findDeletedForUser() is deliberately kept
     * ownership-only, not widened to accepted membership like
     * findAccessibleFor() — only the owner can delete() a shared list in
     * the first place (TaskListPolicy::delete()), so only the owner can
     * undelete it back.
     */
    public function test_find_deleted_for_user_returns_null_for_an_accepted_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();
        $this->repository->delete($list);

        $this->assertNull($this->repository->findDeletedForUser($list->id, $member));
        $this->assertNotNull($this->repository->findDeletedForUser($list->id, $owner));
    }

    /**
     * Plan 4/Step 5: undelete() clears deleted_at and brings back every task
     * the list contained, with its own completion/star/position state
     * intact — the list soft delete never touched them (D2).
     */
    public function test_undelete_restores_the_list_and_every_task_it_held(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Groceries']);
        $active = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $completed = Task::factory()->forTaskList($list)->completed()->create();
        $starred = Task::factory()->forTaskList($list)->starred()->create();
        $this->repository->delete($list);

        $undeleted = $this->repository->undelete($list);

        $this->assertNull($undeleted->deleted_at);
        $this->assertSame('Groceries', $undeleted->name);
        $this->assertNull($active->fresh()->deleted_at);
        $this->assertTrue($completed->fresh()->is_completed);
        $this->assertTrue(TaskStar::query()->where('task_id', $starred->id)->where('user_id', $starred->user_id)->exists());
        $this->assertSame(0, $active->fresh()->position);
    }

    public function test_apply_order_rewrites_positions_within_a_folder_scope(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $a = TaskList::factory()->inFolder($folder, 0)->create();
        $b = TaskList::factory()->inFolder($folder, 1)->create();

        $this->repository->applyOrder($user, $folder->id, [$b->id, $a->id]);

        $this->assertSame(0, $this->positionFor($b, $user));
        $this->assertSame(1, $this->positionFor($a, $user));
    }

    public function test_apply_order_rejects_an_incomplete_list_set_without_writing(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $a = TaskList::factory()->inFolder($folder, 0)->create();
        $b = TaskList::factory()->inFolder($folder, 1)->create();

        try {
            $this->repository->applyOrder($user, $folder->id, [$a->id]);
            $this->fail('Expected a list reorder mismatch.');
        } catch (TaskListReorderMismatchException $exception) {
            $this->assertSame('task_list_reorder_mismatch', $exception->errorCode());
        }

        $this->assertSame(0, $this->positionFor($a, $user));
        $this->assertSame(1, $this->positionFor($b, $user));
    }

    public function test_ungrouped_order_excludes_the_fixed_default_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        $a = TaskList::factory()->atPosition(1)->create(['user_id' => $user->id]);
        $b = TaskList::factory()->atPosition(2)->create(['user_id' => $user->id]);

        $this->repository->applyOrder($user, null, [$b->id, $a->id]);

        $this->assertSame(0, $this->positionFor($inbox, $user));
        $this->assertSame(0, $this->positionFor($b, $user));
        $this->assertSame(1, $this->positionFor($a, $user));
    }

    private function membershipFor(TaskList $list, User $user): TaskListMember
    {
        return TaskListMember::query()
            ->where('task_list_id', $list->id)
            ->where('user_id', $user->id)
            ->sole();
    }

    private function positionFor(TaskList $list, User $user): int
    {
        return $this->membershipFor($list, $user)->position;
    }
}
