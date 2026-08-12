<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Exceptions\TaskReorderMismatchException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\TaskStar;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TaskRepositoryInterface::class);
    }

    public function test_active_for_list_excludes_completed_tasks_and_orders_by_position(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $second = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $first = Task::factory()->forTaskList($list)->create(['position' => 0]);
        Task::factory()->forTaskList($list)->completed()->create();

        $active = $this->repository->activeForList($list, $user);

        $this->assertSame([$first->id, $second->id], $active->pluck('id')->all());
    }

    public function test_active_for_list_returns_an_empty_collection_when_there_are_no_active_tasks(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->repository->activeForList($list, $user)->isEmpty());
    }

    /**
     * Plan 1, Step 3: `is_starred` is aliased for the given viewer, not
     * read off a `tasks` column that no longer exists.
     */
    public function test_active_for_list_aliases_is_starred_for_the_given_viewer_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->starred($owner)->create();

        $forOwner = $this->repository->activeForList($list, $owner)->firstOrFail();
        $forOther = $this->repository->activeForList($list, $other)->firstOrFail();

        $this->assertTrue($forOwner->is($task) && $forOwner->is_starred);
        $this->assertTrue($forOther->is($task) && ! $forOther->is_starred);
    }

    public function test_completed_for_list_orders_by_most_recently_completed_first(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $older = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()->subDay()]);
        $newer = Task::factory()->forTaskList($list)->completed()->create(['completed_at' => now()]);

        $completed = $this->repository->completedForList($list, $user);

        $this->assertSame([$newer->id, $older->id], $completed->pluck('id')->all());
    }

    public function test_starred_for_user_spans_lists_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $listA = TaskList::factory()->create(['user_id' => $user->id]);
        $listB = TaskList::factory()->create(['user_id' => $user->id]);
        $starredA = Task::factory()->forTaskList($listA)->starred()->create();
        $starredB = Task::factory()->forTaskList($listB)->starred()->create();
        Task::factory()->forTaskList($listA)->create();

        $other = User::factory()->create();
        $otherList = TaskList::factory()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherList)->starred()->create();

        $starred = $this->repository->starredForUser($user);

        $this->assertSame(
            [$starredA->id, $starredB->id],
            $starred->pluck('id')->sort()->values()->all(),
        );
        $this->assertTrue($starred->every(fn (Task $task): bool => $task->is_starred === true));
    }

    /**
     * The other side of Plan 1, Step 3's requirement 8: user A starring a
     * task never stars it for user B, even when both can see it (a shared
     * task, modelled directly via a real accepted membership for $userB —
     * sharing invitations are Step 5, not needed to prove this).
     */
    public function test_starring_by_one_user_does_not_star_the_same_task_for_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $userA->id]);
        TaskListMember::factory()->forTaskList($list, $userB)->create();
        $task = Task::factory()->forTaskList($list)->create();

        $this->repository->star($task, $userA);

        $this->assertSame([$task->id], $this->repository->starredForUser($userA)->pluck('id')->all());
        $this->assertTrue($this->repository->starredForUser($userB)->isEmpty());
    }

    public function test_starred_count_for_user_spans_lists_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $listA = TaskList::factory()->create(['user_id' => $user->id]);
        $listB = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($listA)->starred()->create();
        Task::factory()->forTaskList($listB)->starred()->create();
        Task::factory()->forTaskList($listA)->create();

        $other = User::factory()->create();
        $otherList = TaskList::factory()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherList)->starred()->create();

        $this->assertSame(2, $this->repository->starredCountForUser($user));
    }

    public function test_a_deleted_tasks_list_excludes_it_from_starred_count_for_user(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        $this->repository->delete($task);

        $this->assertSame(0, $this->repository->starredCountForUser($user));
    }

    public function test_star_is_idempotent(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->repository->star($task, $user);
        $this->repository->star($task, $user);

        $this->assertSame(1, TaskStar::query()->where('task_id', $task->id)->where('user_id', $user->id)->count());
    }

    public function test_star_returns_the_task_with_is_starred_true(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $starred = $this->repository->star($task, $user);

        $this->assertTrue($starred->is_starred);
    }

    public function test_unstar_is_idempotent_and_harmless_on_an_unstarred_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->repository->unstar($task, $user);
        $this->repository->unstar($task, $user);

        $this->assertSame(0, TaskStar::query()->where('task_id', $task->id)->count());
    }

    public function test_unstar_removes_an_existing_star_and_leaves_others_untouched(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $userA->id]);
        $this->repository->star($task, $userA);
        $this->repository->star($task, $userB);

        $unstarred = $this->repository->unstar($task, $userA);

        $this->assertFalse($unstarred->is_starred);
        $this->assertSame(0, TaskStar::query()->where('task_id', $task->id)->where('user_id', $userA->id)->count());
        $this->assertSame(1, TaskStar::query()->where('task_id', $task->id)->where('user_id', $userB->id)->count());
    }

    public function test_find_for_user_returns_null_for_a_non_member(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertNull($this->repository->findForUser($task->id, $stranger));
        $this->assertNotNull($this->repository->findForUser($task->id, $owner));
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 4: findForUser() is
     * scoped to the viewer's accepted membership on the task's list, not
     * `tasks.user_id` — an accepted non-owner member can resolve a task
     * they did not create, and a pending/declined membership cannot.
     */
    public function test_find_for_user_is_scoped_to_list_membership_not_task_ownership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $pending = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        TaskListMember::factory()->forTaskList($list, $member)->create();
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();

        $this->assertNotNull($this->repository->findForUser($task->id, $member));
        $this->assertNull($this->repository->findForUser($task->id, $pending));
    }

    public function test_find_for_route_binding_resolves_regardless_of_viewer_access_and_aliases_is_starred(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id]);
        $this->repository->star($task, $owner);

        $forOwner = $this->repository->findForRouteBinding($task->id, $owner);
        $forStranger = $this->repository->findForRouteBinding($task->id, $stranger);
        $forGuest = $this->repository->findForRouteBinding($task->id, null);

        $this->assertNotNull($forOwner);
        $this->assertTrue($forOwner->is_starred);
        $this->assertNotNull($forStranger, 'a task must still resolve for a viewer without access, so the policy can 403 it.');
        $this->assertFalse($forStranger->is_starred);
        $this->assertNotNull($forGuest);
    }

    public function test_find_for_route_binding_returns_null_for_a_missing_id(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->repository->findForRouteBinding(999999, $user));
    }

    public function test_next_position_on_an_empty_list_is_zero(): void
    {
        $list = TaskList::factory()->create();

        $this->assertSame(0, $this->repository->nextPosition($list));
    }

    public function test_next_position_on_a_populated_list_continues_the_sequence(): void
    {
        $list = TaskList::factory()->create();
        Task::factory()->forTaskList($list)->create(['position' => 2]);

        $this->assertSame(3, $this->repository->nextPosition($list));
    }

    public function test_mark_completed_sets_is_completed_and_completed_at_and_is_idempotent(): void
    {
        $task = Task::factory()->create();

        $this->repository->markCompleted($task);
        $task->refresh();

        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);

        $completedAt = $task->completed_at;

        $this->repository->markCompleted($task);
        $task->refresh();

        $this->assertTrue($task->completed_at->equalTo($completedAt));
    }

    public function test_mark_active_clears_is_completed_and_completed_at_and_is_idempotent(): void
    {
        $task = Task::factory()->completed()->create();

        $this->repository->markActive($task);
        $task->refresh();

        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);

        $this->repository->markActive($task);
        $task->refresh();

        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
    }

    public function test_update_writes_shared_fields_and_routes_is_starred_to_the_pivot(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);

        $updated = $this->repository->update($task, $user, 'New title', 'A note', null, true);

        $this->assertSame('New title', $updated->title);
        $this->assertSame('A note', $updated->note);
        $this->assertTrue($updated->is_starred);
        $this->assertDatabaseHas('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);
    }

    public function test_update_with_is_starred_false_unstars_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->repository->star($task, $user);

        $updated = $this->repository->update($task, $user, 'Title', null, null, false);
        $updatedAgain = $this->repository->update($task, $user, 'Title', null, null, false);

        $this->assertFalse($updated->is_starred);
        $this->assertFalse($updatedAgain->is_starred);
        $this->assertDatabaseMissing('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);
    }

    public function test_apply_order_rewrites_positions_to_match_the_submitted_order(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);

        $this->repository->applyOrder($list, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_apply_order_rolls_back_and_throws_on_a_mismatched_id_set(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $foreignTask = Task::factory()->create();

        $this->expectException(TaskReorderMismatchException::class);

        try {
            $this->repository->applyOrder($list, [$a->id, $foreignTask->id]);
        } finally {
            $this->assertSame(0, $a->fresh()->position);
            $this->assertSame(1, $b->fresh()->position);
        }
    }

    public function test_apply_order_throws_when_an_id_is_missing_from_the_submitted_set(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        Task::factory()->forTaskList($list)->create(['position' => 1]);

        $this->expectException(TaskReorderMismatchException::class);

        $this->repository->applyOrder($list, [$a->id]);
    }

    /**
     * Regression: the sortable UI only ever submits active-task ids
     * (completed tasks render in a separate, non-sortable section), so
     * idsForList() must be scoped to is_completed = false. Previously it
     * pulled every task in the list, which meant applyOrder() rejected the
     * submitted set as a mismatch on any list that had a completed task.
     */
    public function test_apply_order_succeeds_on_a_list_that_also_has_completed_tasks(): void
    {
        $list = TaskList::factory()->create();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);
        Task::factory()->forTaskList($list)->completed()->create(['position' => 2]);

        $this->repository->applyOrder($list, [$b->id, $a->id]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    /**
     * Plan 4/Step 2: soft-deleted tasks vanish from every read the global
     * scope covers — verified individually so a future removal of the
     * SoftDeletes trait fails loudly here, not just in Step 6's sweep.
     */
    public function test_a_deleted_task_is_invisible_to_active_for_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->repository->delete($task);

        $this->assertTrue($this->repository->activeForList($list, $user)->isEmpty());
    }

    public function test_a_deleted_completed_task_is_invisible_to_completed_for_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->completed()->create();

        $this->repository->delete($task);

        $this->assertTrue($this->repository->completedForList($list, $user)->isEmpty());
    }

    public function test_a_deleted_task_is_invisible_to_starred_for_user(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        $this->repository->delete($task);

        $this->assertTrue($this->repository->starredForUser($user)->isEmpty());
    }

    public function test_a_deleted_task_is_invisible_to_find_for_user(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->repository->delete($task);

        $this->assertNull($this->repository->findForUser($task->id, $user));
    }

    public function test_a_deleted_task_is_invisible_to_ids_for_list(): void
    {
        $list = TaskList::factory()->create();
        $task = Task::factory()->forTaskList($list)->create();

        $this->repository->delete($task);

        $this->assertTrue($this->repository->idsForList($list)->isEmpty());
    }

    /**
     * Plan 4/Step 3; Plan 1, Step 4: findDeletedForUser() is the un-delete
     * lookup — it must return the task only when it is both trashed and the
     * caller has an accepted membership on its list.
     */
    public function test_find_deleted_for_user_returns_a_trashed_task_for_a_member(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $this->repository->delete($task);

        $found = $this->repository->findDeletedForUser($task->id, $user);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($task));
    }

    public function test_find_deleted_for_user_returns_null_for_a_live_task(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->assertNull($this->repository->findDeletedForUser($task->id, $user));
    }

    public function test_find_deleted_for_user_returns_null_for_a_trashed_task_belonging_to_a_non_member(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $this->repository->delete($task);

        $this->assertNull($this->repository->findDeletedForUser($task->id, $stranger));
    }

    /**
     * Plan 1, Step 4: unlike TaskList's findDeletedForUser() (deliberately
     * kept ownership-only), Task's is membership-scoped — an accepted
     * non-owner member can undelete a task in a list they can access, not
     * just the task's own creator.
     */
    public function test_find_deleted_for_user_returns_a_trashed_task_for_an_accepted_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        TaskListMember::factory()->forTaskList($list, $member)->create();
        $this->repository->delete($task);

        $this->assertNotNull($this->repository->findDeletedForUser($task->id, $member));
    }

    public function test_find_deleted_for_user_returns_null_for_a_missing_id(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->repository->findDeletedForUser(999999, $user));
    }

    /**
     * Plan 4/Step 3: undelete() clears deleted_at and every task attribute
     * survives the round trip; calling it twice is harmless.
     */
    public function test_undelete_clears_deleted_at_and_restores_the_task_to_its_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create([
            'title' => 'Buy milk',
            'note' => 'Whole milk',
            'position' => 3,
        ]);
        $this->repository->delete($task);

        $undeleted = $this->repository->undelete($task);

        $this->assertNull($undeleted->deleted_at);
        $this->assertSame('Buy milk', $undeleted->title);
        $this->assertSame('Whole milk', $undeleted->note);
        $this->assertSame(3, $undeleted->position);
        $this->assertDatabaseHas('task_stars', ['task_id' => $task->id, 'user_id' => $user->id]);
        $this->assertTrue($this->repository->activeForList($list, $user)->contains(fn (Task $t): bool => $t->is($task)));
    }

    public function test_undeleting_an_already_live_task_is_harmless(): void
    {
        $task = Task::factory()->create();
        $this->repository->delete($task);
        $this->repository->undelete($task);

        $this->repository->undelete($task);

        $this->assertNull($task->fresh()->deleted_at);
    }

    public function test_apply_order_rejects_a_trashed_tasks_id_as_a_mismatch(): void
    {
        $list = TaskList::factory()->create();
        $active = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $trashed = Task::factory()->forTaskList($list)->create(['position' => 1]);
        $this->repository->delete($trashed);

        $this->expectException(TaskReorderMismatchException::class);

        try {
            $this->repository->applyOrder($list, [$trashed->id, $active->id]);
        } finally {
            $this->assertSame(0, $active->fresh()->position);
        }
    }
}
