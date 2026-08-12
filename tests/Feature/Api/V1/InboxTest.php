<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->getJson('/api/v1/inbox')->assertStatus(401);
    }

    public function test_it_returns_the_documented_shape_with_active_and_completed_tasks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/inbox');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'list' => ['id', 'name', 'is_default'],
                'tasks' => ['active', 'completed', 'completed_count'],
            ],
        ]);
        $response->assertJsonPath('data.list.is_default', true);
    }

    /**
     * Regression: `findDefaultFor()` must carry the same viewer-relative
     * placement `allForUser()`'s join produces (Plan 1, Step 2 review
     * follow-up) — the Inbox is always ungrouped at position 0 (D8/A2), so
     * a wire-format `"position": null` here is a real V1 contract break,
     * not just a cosmetic gap.
     */
    public function test_it_reports_the_inboxs_placement_as_ungrouped_at_position_zero(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/inbox');

        $response->assertOk();
        $response->assertJsonPath('data.list.folder_id', null);
        $response->assertJsonPath('data.list.position', 0);
    }

    public function test_active_tasks_precede_completed_tasks_and_completed_tasks_are_most_recent_first(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);

        $active = Task::factory()->forTaskList($inbox)->create(['title' => 'Active task', 'position' => 0]);
        $olderCompleted = Task::factory()->forTaskList($inbox)->completed()->create([
            'title' => 'Older completed',
            'completed_at' => now()->subDay(),
        ]);
        $newerCompleted = Task::factory()->forTaskList($inbox)->completed()->create([
            'title' => 'Newer completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/inbox');

        $response->assertJsonPath('data.tasks.active.0.id', $active->id);
        $response->assertJsonPath('data.tasks.completed.0.id', $newerCompleted->id);
        $response->assertJsonPath('data.tasks.completed.1.id', $olderCompleted->id);
        $response->assertJsonPath('data.tasks.completed_count', 2);
    }

    public function test_another_users_tasks_never_appear(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherInbox = TaskList::factory()->inbox()->create(['user_id' => $other->id]);
        Task::factory()->forTaskList($otherInbox)->create(['title' => 'Not mine']);

        $response = $this->actingAs($user)->getJson('/api/v1/inbox');

        $response->assertJsonCount(0, 'data.tasks.active');
    }
}
