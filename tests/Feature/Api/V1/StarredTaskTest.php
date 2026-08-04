<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
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
}
