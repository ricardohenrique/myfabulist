<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskStar;
use App\Models\User;
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
     * Indicate that the task is starred (Plan 1, Step 3: a `task_stars` row,
     * not an `is_starred` column). `$user` defaults to the task's own
     * creator (`user_id`) once it exists — the pre-sharing shape every
     * existing call site (`->starred()` with no argument) still gets — but
     * accepts an explicit, different user so tests can star a shared task
     * as a specific collaborator without starring it for its creator too.
     *
     * `afterCreating()` rather than a `state()` attribute because starring
     * is no longer a `tasks` column at all — it has to happen after the
     * task row (and its id) exist.
     */
    public function starred(?User $user = null): static
    {
        return $this->afterCreating(function (Task $task) use ($user) {
            TaskStar::factory()->create([
                'task_id' => $task->id,
                'user_id' => $user === null ? $task->user_id : $user->id,
            ]);
        });
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
