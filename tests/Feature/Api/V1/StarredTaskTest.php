<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Folder;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarredTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/starred')->assertStatus(401);
    }

    public function test_it_returns_only_the_users_starred_tasks_across_lists(): void
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

        $response = $this->actingAs($user)->getJson('/api/v1/starred');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$starredA->id, $starredB->id], $ids);
    }

    public function test_it_does_not_incur_n_plus_one_queries(): void
    {
        $user = User::factory()->create();
        $listA = TaskList::factory()->create(['user_id' => $user->id]);
        $listB = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($listA)->starred()->count(3)->create();
        Task::factory()->forTaskList($listB)->starred()->count(3)->create();

        // Model::preventLazyLoading() (Step 1) turns any N+1 regression into
        // an exception, so a 200 here is itself the guarantee.
        $this->actingAs($user)->getJson('/api/v1/starred')->assertOk();
    }

    public function test_it_returns_an_empty_list_when_the_user_has_no_starred_tasks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/starred');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    /**
     * Plan 1, Step 3 (F8): starring is per-viewer. Two accepted members of
     * the same shared list starring the same task independently (the
     * membership modelled directly via the factory, ahead of real
     * list-sharing invitations in Step 5) each see it in their own Starred
     * view, with `is_starred: true` for both — starring is never a single
     * shared flag on the task.
     */
    public function test_the_starred_view_only_includes_the_viewers_own_star_on_a_task_starred_by_two_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $userA->id]);
        TaskListMember::factory()->forTaskList($list, $userB)->create();
        $task = Task::factory()->forTaskList($list)->create();

        $tasks = app(TaskRepositoryInterface::class);
        $tasks->star($task, $userA);
        $tasks->star($task, $userB);

        $responseA = $this->actingAs($userA)->getJson('/api/v1/starred');
        $responseB = $this->actingAs($userB)->getJson('/api/v1/starred');

        $responseA->assertJsonPath('data.0.id', $task->id);
        $responseA->assertJsonPath('data.0.is_starred', true);
        $responseB->assertJsonPath('data.0.id', $task->id);
        $responseB->assertJsonPath('data.0.is_starred', true);
    }

    /**
     * Plan 1, Step 4 (code-review follow-up): a `task_stars` row is never
     * itself an access grant. A user with zero relationship to the task's
     * list (no membership row at all) must not see it via Starred even if a
     * star row somehow exists for them — the repository is the enforcement
     * point, not merely "nothing today lets you create that star row".
     * Same for a merely-pending member. Both stars are written directly via
     * the repository, bypassing `TaskPolicy` entirely, to prove the read
     * path itself denies regardless of how the star row got there.
     */
    public function test_a_non_member_or_pending_members_star_never_leaks_the_task_via_starred(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $pending = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $pending)->pending()->create();
        $task = Task::factory()->forTaskList($list)->create();

        $tasks = app(TaskRepositoryInterface::class);
        $tasks->star($task, $stranger);
        $tasks->star($task, $pending);

        $this->actingAs($stranger)->getJson('/api/v1/starred')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($pending)->getJson('/api/v1/starred')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, $tasks->starredCountForUser($stranger));
        $this->assertSame(0, $tasks->starredCountForUser($pending));
    }

    /**
     * Regression (Plan 1, Step 3 review): the same class of bug Step 2's
     * review caught for `GET /api/v1/inbox` — the embedded `list` on each
     * starred task must carry its *real* placement, not `null`, because
     * `starredForUser()`'s `->with('taskList.folder')` eager load never
     * chained `TaskList::scopeJoinMemberPlacement()` on its own.
     */
    public function test_the_starred_view_embeds_each_tasks_list_with_its_real_placement(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->for($user)->create();
        $list = TaskList::factory()->inFolder($folder, 3)->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->starred()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/starred');

        $response->assertOk();
        $response->assertJsonPath('data.0.list.folder_id', $folder->id);
        $response->assertJsonPath('data.0.list.position', 3);
    }

    /**
     * The sidebar's Starred badge (starredCountForUser) counts only the
     * viewer's own stars, even on a task multiple users have starred.
     */
    public function test_starred_count_only_counts_the_viewers_own_stars(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $userA->id]);
        TaskListMember::factory()->forTaskList($list, $userB)->create();
        $task = Task::factory()->forTaskList($list)->create();

        $tasks = app(TaskRepositoryInterface::class);
        $tasks->star($task, $userA);
        $tasks->star($task, $userB);

        $this->assertSame(1, $tasks->starredCountForUser($userA));
        $this->assertSame(1, $tasks->starredCountForUser($userB));
    }
}
