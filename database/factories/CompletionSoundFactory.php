<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CompletionSound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompletionSound>
 */
class CompletionSoundFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(10, 999);

        return [
            'key' => 'sound_effect_'.$number,
            'label' => 'Sound '.$number,
            'file_path' => 'sound-effect-'.$number.'.mp3',
            'enabled' => true,
            'is_default' => false,
            'sort_order' => $number,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
