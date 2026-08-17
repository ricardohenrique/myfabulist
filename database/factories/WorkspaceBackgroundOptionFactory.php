<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkspaceBackgroundOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceBackgroundOption>
 */
class WorkspaceBackgroundOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'type' => 'flat_color',
            'label' => fake()->words(2, true),
            'default_config' => null,
            'enabled' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the option is hidden from new selections.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Attach a curated preset config, ready to be adopted by
     * `WorkspaceBackgroundService::updateSelection()` when the caller
     * submits an empty config.
     *
     * @param  array<string, mixed>  $config
     */
    public function withDefaultConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'default_config' => $config,
        ]);
    }
}
