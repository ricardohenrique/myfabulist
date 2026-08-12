<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 4: end-to-end proof, over
 * real HTTP (both `/api/v1` and the web surface), that authorization now
 * resolves through list membership rather than ownership. Every membership
 * row here is constructed directly via the factory — the invite/accept UI
 * does not exist yet (Step 5) — but the authorization boundary these
 * requests exercise is exactly the one Step 5 will hand real membership rows
 * to. This is also the harness that would surface a
 * `Model::preventLazyLoading()` violation from the new
 * `TaskPolicy`/`SubtaskPolicy` delegation, or a web/API validation-rule
 * parity break (code-review follow-up, see `UpdateTaskRequest`), since these
 * requests reach the policies/Form Requests through the ordinary route-bound
 * models, not a hand-authorized bypass.
 */
class AcceptedMembershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_accepted_non_owner_member_can_view_and_rename_the_shared_list(): void
    {
        [$owner, $member, $list] = $this->sharedList();

        $this->actingAs($member)->getJson("/api/v1/lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $list->id);

        $this->actingAs($member)->putJson("/api/v1/lists/{$list->id}", ['name' => 'Renamed by member'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed by member');

        $this->assertSame('Renamed by member', $list->fresh()->name);
    }

    public function test_an_accepted_non_owner_member_cannot_delete_share_or_manage_the_list(): void
    {
        [$owner, $member, $list] = $this->sharedList();

        $this->actingAs($member)->deleteJson("/api/v1/lists/{$list->id}")->assertForbidden();
        $this->assertNull($list->fresh()->deleted_at);
    }

    public function test_an_accepted_non_owner_member_can_create_view_update_star_comment_on_and_complete_a_task(): void
    {
        [$owner, $member, $list] = $this->sharedList();

        $created = $this->actingAs($member)->postJson("/api/v1/lists/{$list->id}/tasks", [
            'title' => 'Collaborator-created task',
        ])->assertCreated()->json('data');

        $this->actingAs($member)->getJson("/api/v1/tasks/{$created['id']}")->assertOk();

        $this->actingAs($member)->putJson("/api/v1/tasks/{$created['id']}", [
            'title' => 'Renamed by member',
            'is_starred' => true,
        ])->assertOk()->assertJsonPath('data.is_starred', true);

        $this->actingAs($member)->postJson("/api/v1/tasks/{$created['id']}/comments", [
            'body' => 'Left by a collaborator',
        ])->assertCreated();

        $this->actingAs($member)->postJson("/api/v1/tasks/{$created['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);
    }

    public function test_an_accepted_non_owner_member_can_reorder_tasks_in_the_shared_list(): void
    {
        [$owner, $member, $list] = $this->sharedList();
        $a = Task::factory()->forTaskList($list)->create(['position' => 0]);
        $b = Task::factory()->forTaskList($list)->create(['position' => 1]);

        $this->actingAs($member)->putJson("/api/v1/lists/{$list->id}/task-order", [
            'task_ids' => [$b->id, $a->id],
        ])->assertNoContent();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_an_accepted_non_owner_member_can_create_update_and_complete_a_subtask(): void
    {
        [$owner, $member, $list] = $this->sharedList();
        $task = Task::factory()->forTaskList($list)->create();

        $subtaskId = $this->actingAs($member)->postJson("/api/v1/tasks/{$task->id}/subtasks", [
            'title' => 'Sub-step',
        ])->assertCreated()->json('data.id');

        $this->actingAs($member)->putJson("/api/v1/subtasks/{$subtaskId}", ['title' => 'Renamed sub-step'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed sub-step');

        $this->actingAs($member)->postJson("/api/v1/subtasks/{$subtaskId}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);
    }

    /**
     * Code-review follow-up (Medium finding #4): allForUser() now includes
     * shared lists, so a real `/api/v1/lists/order` reorder submitted by a
     * non-owner accepted member must accept the shared list's id alongside
     * the member's own lists — the old ownership-scoped validation rule
     * would have rejected it while the repository's "complete id set" check
     * simultaneously rejected omitting it, making the container permanently
     * unreorderable for anyone but the owner.
     */
    public function test_an_accepted_non_owner_members_shared_list_can_be_included_in_a_real_list_reorder_payload(): void
    {
        [$owner, $member, $sharedList] = $this->sharedList();
        $ownList = TaskList::factory()->create(['user_id' => $member->id]);

        $this->actingAs($member)->putJson('/api/v1/lists/order', [
            'folder_id' => null,
            'task_list_ids' => [$sharedList->id, $ownList->id],
        ])->assertNoContent();
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function apiMemberRoutes(): iterable
    {
        yield 'GET list' => ['getJson', '/api/v1/lists/{list}', []];
        yield 'PUT list' => ['putJson', '/api/v1/lists/{list}', ['name' => 'Attempted']];
        yield 'DELETE list' => ['deleteJson', '/api/v1/lists/{list}', []];
        yield 'GET list tasks' => ['getJson', '/api/v1/lists/{list}/tasks', []];
        yield 'POST list tasks (store)' => ['postJson', '/api/v1/lists/{list}/tasks', ['title' => 'Attempted']];
        yield 'PUT list task-order' => ['putJson', '/api/v1/lists/{list}/task-order', ['task_ids' => [1]]];
        yield 'GET task' => ['getJson', '/api/v1/tasks/{task}', []];
        yield 'PUT task (update)' => ['putJson', '/api/v1/tasks/{task}', ['title' => 'Attempted', 'is_starred' => false]];
        yield 'DELETE task' => ['deleteJson', '/api/v1/tasks/{task}', []];
        yield 'POST task complete' => ['postJson', '/api/v1/tasks/{task}/complete', []];
        yield 'POST task restore' => ['postJson', '/api/v1/tasks/{task}/restore', []];
        yield 'POST task move' => ['postJson', '/api/v1/tasks/{task}/move', ['task_list_id' => 999999]];
        yield 'GET task comments' => ['getJson', '/api/v1/tasks/{task}/comments', []];
        yield 'POST task comments (store)' => ['postJson', '/api/v1/tasks/{task}/comments', ['body' => 'Attempted']];
        yield 'GET task subtasks' => ['getJson', '/api/v1/tasks/{task}/subtasks', []];
        yield 'POST task subtasks (store)' => ['postJson', '/api/v1/tasks/{task}/subtasks', ['title' => 'Attempted']];
        yield 'PUT subtask (update)' => ['putJson', '/api/v1/subtasks/{subtask}', ['title' => 'Attempted']];
        yield 'DELETE subtask' => ['deleteJson', '/api/v1/subtasks/{subtask}', []];
        yield 'POST subtask complete' => ['postJson', '/api/v1/subtasks/{subtask}/complete', []];
        yield 'POST subtask restore' => ['postJson', '/api/v1/subtasks/{subtask}/restore', []];
    }

    /**
     * Every route in `apiMemberRoutes()` must deny a pending member, a
     * declined member, and a complete stranger (no membership row at all)
     * alike — the "not yet/no longer/never a member" states all collapse to
     * the same denial, since `isAcceptedMember()` is the sole predicate.
     */
    #[DataProvider('apiMemberRoutes')]
    public function test_every_non_accepted_or_non_member_state_is_denied_on_every_api_route(
        string $method,
        string $pathTemplate,
        array $payload,
    ): void {
        foreach (['pending', 'declined', 'non-member'] as $state) {
            $scenario = $this->scenario($state);
            $path = $this->resolvePath($pathTemplate, $scenario);

            $this->actingAs($scenario['user'])->{$method}($path, $payload)
                ->assertForbidden();
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function webMemberRoutes(): iterable
    {
        yield 'GET list show' => ['get', '/lists/{list}', []];
        yield 'PUT list update' => ['put', '/lists/{list}', ['name' => 'Attempted']];
        yield 'DELETE list destroy' => ['delete', '/lists/{list}', []];
        yield 'POST list tasks (store)' => ['post', '/lists/{list}/tasks', ['title' => 'Attempted']];
        yield 'PUT list task-order' => ['put', '/lists/{list}/task-order', ['task_ids' => [1]]];
        yield 'PUT task (update)' => ['put', '/tasks/{task}', [
            'title' => 'Attempted', 'note' => null, 'due_date' => null, 'is_starred' => false, 'task_list_id' => 1,
        ]];
        yield 'DELETE task' => ['delete', '/tasks/{task}', []];
        yield 'POST task complete' => ['post', '/tasks/{task}/complete', []];
        yield 'POST task restore' => ['post', '/tasks/{task}/restore', []];
        yield 'PUT task star' => ['put', '/tasks/{task}/star', ['is_starred' => true]];
        yield 'POST task move' => ['post', '/tasks/{task}/move', ['task_list_id' => 999999]];
        yield 'POST task comments (store)' => ['post', '/tasks/{task}/comments', ['body' => 'Attempted']];
        yield 'POST task subtasks (store)' => ['post', '/tasks/{task}/subtasks', ['title' => 'Attempted']];
        yield 'PUT subtask (update)' => ['put', '/subtasks/{subtask}', ['title' => 'Attempted']];
        yield 'DELETE subtask' => ['delete', '/subtasks/{subtask}', []];
        yield 'POST subtask complete' => ['post', '/subtasks/{subtask}/complete', []];
        yield 'POST subtask restore' => ['post', '/subtasks/{subtask}/restore', []];
    }

    /**
     * The web-surface sibling of `test_every_non_accepted_or_non_member_state_is_denied_on_every_api_route()`
     * — the previous version of this class only exercised `/api/v1`, which
     * is exactly why the `Web\UpdateTaskRequest` validation-rule parity
     * break (High finding #2) went uncaught.
     */
    #[DataProvider('webMemberRoutes')]
    public function test_every_non_accepted_or_non_member_state_is_denied_on_every_web_route(
        string $method,
        string $pathTemplate,
        array $payload,
    ): void {
        foreach (['pending', 'declined', 'non-member'] as $state) {
            $scenario = $this->scenario($state);
            $path = $this->resolvePath($pathTemplate, $scenario);

            $this->actingAs($scenario['user'])->{$method}($path, $payload)
                ->assertForbidden();
        }
    }

    /**
     * @return array{0: User, 1: User, 2: TaskList}
     */
    private function sharedList(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        TaskListMember::factory()->forTaskList($list, $member)->create();

        return [$owner, $member, $list];
    }

    /**
     * Builds an owner + list + task + subtask, plus a $user whose
     * relationship to the list matches $state: 'pending' or 'declined' (a
     * real membership row in that status) or 'non-member' (no row at all).
     *
     * @return array{user: User, list: TaskList, task: Task, subtask: Subtask}
     */
    private function scenario(string $state): array
    {
        $owner = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $subtask = Subtask::factory()->forTask($task)->create();
        $user = User::factory()->create();

        match ($state) {
            'pending' => TaskListMember::factory()->forTaskList($list, $user)->pending()->create(),
            'declined' => TaskListMember::factory()->forTaskList($list, $user)->create(['status' => 'declined']),
            'non-member' => null,
            default => throw new \InvalidArgumentException("Unknown membership state: {$state}"),
        };

        return ['user' => $user, 'list' => $list, 'task' => $task, 'subtask' => $subtask];
    }

    /**
     * @param  array{user: User, list: TaskList, task: Task, subtask: Subtask}  $scenario
     */
    private function resolvePath(string $template, array $scenario): string
    {
        return strtr($template, [
            '{list}' => (string) $scenario['list']->id,
            '{task}' => (string) $scenario['task']->id,
            '{subtask}' => (string) $scenario['subtask']->id,
        ]);
    }
}
