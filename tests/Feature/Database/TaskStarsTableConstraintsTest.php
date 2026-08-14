<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Task;
use App\Models\TaskStar;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 3: the schema-level
 * guarantees `EloquentTaskRepository::star()`/`unstar()` depend on — the
 * unique `(task_id, user_id)` pair (duplicate-star prevention) and cascade
 * deletes on both `task_id` and `user_id`.
 */
class TaskStarsTableConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_have_two_star_rows_for_the_same_task(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();
        TaskStar::factory()->create(['task_id' => $task->id, 'user_id' => $user->id]);

        $this->expectException(QueryException::class);

        TaskStar::factory()->create(['task_id' => $task->id, 'user_id' => $user->id]);
    }

    public function test_deleting_a_task_cascades_to_its_star_rows(): void
    {
        $task = Task::factory()->create();
        TaskStar::factory()->create(['task_id' => $task->id]);

        $task->forceDelete();

        $this->assertSame(0, TaskStar::query()->where('task_id', $task->id)->count());
    }

    public function test_deleting_a_user_cascades_to_their_star_rows(): void
    {
        $user = User::factory()->create();
        $star = TaskStar::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('task_stars', ['id' => $star->id]);
    }
}
