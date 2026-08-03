<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskList>
 */
class TaskListFactory extends Factory
{
    protected $model = TaskList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'folder_id' => null,
            'name' => fake()->words(2, true),
            'is_default' => false,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the list belongs to the given folder.
     */
    public function inFolder(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $folder->user_id,
            'folder_id' => $folder->id,
        ]);
    }

    /**
     * Indicate that the list is the user's default Inbox.
     */
    public function inbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Inbox',
            'folder_id' => null,
            'is_default' => true,
            'position' => 0,
        ]);
    }
}
