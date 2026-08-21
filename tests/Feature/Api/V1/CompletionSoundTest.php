<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CompletionSound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionSoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->patchJson('/api/v1/profile/completion-sound', ['completion_sound_key' => null])->assertStatus(401);
    }

    public function test_user_can_select_a_sound(): void
    {
        $sound = CompletionSound::query()->where('key', 'sound_effect_02')->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/profile/completion-sound', ['completion_sound_key' => $sound->key])
            ->assertOk()
            ->assertJsonPath('data.key', 'sound_effect_02')
            ->assertJsonPath('data.label', 'Sound 2')
            ->assertJsonPath('data.url', '/sound-effect-02.mp3');
    }

    public function test_user_can_choose_no_sound(): void
    {
        $sound = CompletionSound::query()->where('is_default', true)->firstOrFail();
        $user = User::factory()->withCompletionSound($sound)->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/profile/completion-sound', ['completion_sound_key' => null])
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_unknown_sound_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/profile/completion-sound', ['completion_sound_key' => 'missing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('completion_sound_key');
    }
}
