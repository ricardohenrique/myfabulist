<?php

namespace Database\Factories;

use App\Models\CompletionSound;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'google_id' => null,
            'avatar' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            // No default workspace background — a freshly factory-made user
            // must render the workspace exactly like today's hard-coded
            // colors (Plan: "Workspace Background Personalization", Step 1).
            'workspace_background_option_id' => null,
            'workspace_background_config' => null,
            // Factory users opt out unless a test explicitly selects a sound.
            'completion_sound_id' => null,
            // Factory and seeded users represent established accounts.
            // Real registrations omit these fields and receive the prompt.
            'onboarding_use_case' => null,
            'onboarding_completed_at' => now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has not answered or skipped onboarding yet.
     */
    public function pendingOnboarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_use_case' => null,
            'onboarding_completed_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model has a profile photo stored.
     */
    public function withProfilePhoto(string $path = 'profile-photos/test-photo.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_photo_path' => $path,
        ]);
    }

    /**
     * Indicate that the user has an existing workspace background selection.
     *
     * @param  array<string, mixed>  $config
     */
    public function withWorkspaceBackground(WorkspaceBackgroundOption $option, array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_background_option_id' => $option->id,
            'workspace_background_config' => $config,
        ]);
    }

    public function withCompletionSound(CompletionSound $sound): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_sound_id' => $sound->id,
        ]);
    }
}
