<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task::dueDateStatus() (D3): pure derivation from due_date + is_completed,
 * compared against today() rather than now() (R3).
 */
class TaskDueDateStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_null_when_there_is_no_due_date(): void
    {
        $task = Task::factory()->create(['due_date' => null]);

        $this->assertNull($task->dueDateStatus());
    }

    public function test_it_is_overdue_for_a_date_in_the_past(): void
    {
        $task = Task::factory()->create(['due_date' => today()->subDay()]);

        $this->assertSame('overdue', $task->dueDateStatus());
    }

    public function test_it_is_today_for_todays_date(): void
    {
        $task = Task::factory()->create(['due_date' => today()]);

        $this->assertSame('today', $task->dueDateStatus());
    }

    public function test_it_is_upcoming_for_a_future_date(): void
    {
        $task = Task::factory()->create(['due_date' => today()->addDay()]);

        $this->assertSame('upcoming', $task->dueDateStatus());
    }

    public function test_a_completed_overdue_task_is_not_reported_as_overdue(): void
    {
        $task = Task::factory()->create([
            'due_date' => today()->subDay(),
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->assertNotSame('overdue', $task->dueDateStatus());
    }
}
