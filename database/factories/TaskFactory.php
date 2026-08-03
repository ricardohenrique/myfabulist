<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskList;
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
