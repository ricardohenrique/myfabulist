<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceBackgroundManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_route_requires_authentication(): void
    {
        $this->patch(route('profile.background.update'))->assertRedirect(route('login'));
    }

    public function test_user_can_select_a_flat_color_background(): void
    {
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.background.update'), [
                'option_key' => 'flat_color',
                'color' => '#112233',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();
        $this->assertSame($option->id, $user->workspace_background_option_id);
        $this->assertSame(['color' => '#112233'], $user->workspace_background_config);
    }

    public function test_user_can_select_a_gradient_background(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'gradient', 'type' => 'gradient']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.background.update'), [
                'option_key' => 'gradient',
                'gradient_from' => '#112233',
                'gradient_to' => '#445566',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            ['from' => '#112233', 'to' => '#445566'],
            $user->fresh()->workspace_background_config,
        );
    }

    public function test_user_can_select_an_image_background(): void
    {
        Storage::fake('public');
        WorkspaceBackgroundOption::factory()->create(['key' => 'image', 'type' => 'image']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.background.update'), [
                'option_key' => 'image',
                'image' => UploadedFile::fake()->image('bg.jpg', 800, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $config = $user->fresh()->workspace_background_config;
        $this->assertArrayHasKey('path', $config);
        Storage::disk('public')->assertExists($config['path']);
    }

    public function test_validation_fails_for_an_invalid_color(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('profile.background.update'), [
                'option_key' => 'flat_color',
                'color' => 'not-a-color',
            ])
            ->assertSessionHasErrors('color')
            ->assertRedirect(route('inbox'));

        $this->assertNull($user->fresh()->workspace_background_option_id);
    }

    public function test_an_unknown_option_key_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('profile.background.update'), [
                'option_key' => 'does-not-exist',
                'color' => '#112233',
            ])
            ->assertSessionHasErrors('option_key')
            ->assertRedirect(route('inbox'));
    }

    /**
     * The DomainException -> back()->withErrors(['domain' => ...]) branch
     * only fires for a request carrying the X-Inertia header — see
     * bootstrap/app.php and the equivalent idiom in SharedListWebTest.php.
     */
    public function test_selecting_a_disabled_option_is_rejected_as_a_domain_error(): void
    {
        WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->from(route('inbox'))
            ->patch(route('profile.background.update'), [
                'option_key' => 'flat_color',
                'color' => '#112233',
            ])
            ->assertRedirect(route('inbox'))
            ->assertSessionHasErrors('domain');

        $this->assertNull($user->fresh()->workspace_background_option_id);
    }

    public function test_user_can_clear_their_background_selection(): void
    {
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->withWorkspaceBackground($option, ['color' => '#112233'])->create();

        $this->actingAs($user)
            ->patch(route('profile.background.update'), ['option_key' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->workspace_background_option_id);
        $this->assertNull($user->workspace_background_config);
    }

    public function test_updating_a_background_never_touches_another_users_selection(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($actingUser)->patch(route('profile.background.update'), [
            'option_key' => 'flat_color',
            'color' => '#445566',
        ]);

        $this->assertNull($otherUser->fresh()->workspace_background_option_id);
    }
}
