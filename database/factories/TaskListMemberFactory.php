<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskListMember>
 */
class TaskListMemberFactory extends Factory
{
    protected $model = TaskListMember::class;

    /**
     * Default state is a valid accepted membership — the shape every
     * pre-existing list gets for its owner (Step 1's backfill).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_list_id' => TaskList::factory(),
            'user_id' => User::factory(),
            'status' => 'accepted',
            'folder_id' => null,
            'position' => 0,
            'invited_by_user_id' => null,
            'invited_at' => null,
            'responded_at' => now(),
        ];
    }

    /**
     * Indicate that the membership belongs to the given list, defaulting
     * the member to the list's own owner when no user is given.
     */
    public function forTaskList(TaskList $taskList, ?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'task_list_id' => $taskList->id,
            'user_id' => $user->id ?? $taskList->user_id,
        ]);
    }

    /**
     * Indicate a not-yet-responded invitation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'responded_at' => null,
        ]);
    }
}
