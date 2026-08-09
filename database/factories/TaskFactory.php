<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskList;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_list_id' => TaskList::factory(),
            'title' => fake()->sentence(4),
            'note' => null,
            'is_completed' => false,
            'completed_at' => null,
            'is_starred' => false,
            'due_date' => null,
            'position' => 0,
        ];
    }

    /**
     * Set the owning user and list from the given task list.
     */
    public function forTaskList(TaskList $taskList): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $taskList->user_id,
            'task_list_id' => $taskList->id,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is starred.
     */
    public function starred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_starred' => true,
        ]);
    }

    /**
     * Attach a note. Accepts an explicit note (e.g. from `DemoContent`) so
     * callers outside the test suite are not forced into `fake()->sentence()`.
     */
    public function withNote(?string $note = null): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note ?? fake()->sentence(),
        ]);
    }

    /**
     * Due today — the `dueDateStatus()` "today" branch.
     */
    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today(),
        ]);
    }

    /**
     * Due 1–14 days in the past — the `dueDateStatus()` "overdue" branch
     * (unless the task is also completed, which suppresses it).
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today()->subDays(fake()->numberBetween(1, 14)),
        ]);
    }

    /**
     * Due 1–21 days in the future — the `dueDateStatus()` "upcoming" branch.
     */
    public function dueUpcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => today()->addDays(fake()->numberBetween(1, 21)),
        ]);
    }

    /**
     * Mark the task completed at a specific point in time, unlike
     * `completed()` which always stamps `now()`. Demo data needs *spread*
     * completion timestamps so `completedForList()`'s `completed_at DESC`
     * ordering is meaningful instead of collapsing to id order.
     */
    public function completedAt(CarbonInterface $at): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => $at,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Task $task) {
            if (! $task->user_id) {
                $task->user_id = TaskList::query()->find($task->task_list_id)?->user_id;
            }
        });
    }
}
