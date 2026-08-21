<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CompletionSound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionSoundManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_requires_authentication(): void
    {
        $this->patch(route('profile.completion-sound.update'))->assertRedirect(route('login'));
    }

    public function test_user_can_select_a_completion_sound(): void
    {
        $sound = CompletionSound::query()->where('key', 'sound_effect_02')->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.completion-sound.update'), ['completion_sound_key' => $sound->key])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Completion sound updated.')
            ->assertRedirect();

        $this->assertSame($sound->id, $user->fresh()->completion_sound_id);
    }

    public function test_user_can_choose_no_sound(): void
    {
        $sound = CompletionSound::query()->where('is_default', true)->firstOrFail();
        $user = User::factory()->withCompletionSound($sound)->create();

        $this->actingAs($user)
            ->patch(route('profile.completion-sound.update'), ['completion_sound_key' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->completion_sound_id);
    }

    public function test_unknown_sound_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('profile.completion-sound.update'), ['completion_sound_key' => 'missing'])
            ->assertSessionHasErrors('completion_sound_key')
            ->assertRedirect(route('inbox'));
    }

    public function test_updating_a_sound_does_not_touch_another_user(): void
    {
        $sound = CompletionSound::query()->where('key', 'sound_effect_03')->firstOrFail();
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($actingUser)->patch(route('profile.completion-sound.update'), [
            'completion_sound_key' => $sound->key,
        ]);

        $this->assertNull($otherUser->fresh()->completion_sound_id);
    }
}
