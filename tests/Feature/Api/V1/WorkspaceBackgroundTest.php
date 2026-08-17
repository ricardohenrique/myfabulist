<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_unauthorized(): void
    {
        $this->patchJson('/api/v1/profile/background', ['option_key' => null])->assertStatus(401);
    }

    public function test_a_user_can_select_a_flat_color_background(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', [
            'option_key' => 'flat_color',
            'color' => '#112233',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.optionKey', 'flat_color');
        $response->assertJsonPath('data.type', 'flat_color');
        $response->assertJsonPath('data.config.color', '#112233');
        $this->assertSame(['color' => '#112233'], $user->fresh()->workspace_background_config);
    }

    public function test_a_user_can_select_an_image_background_and_receives_a_public_url(): void
    {
        Storage::fake('public');
        WorkspaceBackgroundOption::factory()->create(['key' => 'image', 'type' => 'image']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', [
            'option_key' => 'image',
            'image' => UploadedFile::fake()->image('bg.jpg', 800, 600),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.type', 'image');
        $this->assertIsString($response->json('data.config.url'));
        $this->assertStringContainsString('workspace-backgrounds', (string) $response->json('data.config.url'));
    }

    public function test_a_user_can_clear_their_background_selection(): void
    {
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->withWorkspaceBackground($option, ['color' => '#112233'])->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', ['option_key' => null]);

        $response->assertOk();
        $response->assertExactJson(['data' => null]);
        $this->assertNull($user->fresh()->workspace_background_option_id);
    }

    public function test_selecting_a_disabled_option_is_rejected_with_its_error_code(): void
    {
        WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', [
            'option_key' => 'flat_color',
            'color' => '#112233',
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'invalid_background_selection');
        $this->assertNull($user->fresh()->workspace_background_option_id);
    }

    public function test_an_unknown_option_key_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', [
            'option_key' => 'does-not-exist',
            'color' => '#112233',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('option_key');
    }

    public function test_an_invalid_color_fails_validation(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/background', [
            'option_key' => 'flat_color',
            'color' => 'not-a-color',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('color');
    }

    public function test_updating_a_background_never_touches_another_users_selection(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($actingUser)->patchJson('/api/v1/profile/background', [
            'option_key' => 'flat_color',
            'color' => '#445566',
        ]);

        $this->assertNull($otherUser->fresh()->workspace_background_option_id);
    }
}
