<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\InvalidBackgroundSelectionException;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceBackgroundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_an_enabled_option_succeeds(): void
    {
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'flat_color', ['color' => '#AABBCC']);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertSame(['color' => '#aabbcc'], $updated->workspace_background_config);
    }

    public function test_selecting_a_disabled_option_throws(): void
    {
        WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $this->expectException(InvalidBackgroundSelectionException::class);

        $service->updateSelection($user, 'flat_color', ['color' => '#aabbcc']);
    }

    public function test_a_user_already_on_a_disabled_option_can_resave_it(): void
    {
        $option = WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->withWorkspaceBackground($option, ['color' => '#aabbcc'])->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'flat_color', ['color' => '#112233']);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertSame(['color' => '#112233'], $updated->workspace_background_config);
    }

    public function test_selecting_an_unknown_key_throws(): void
    {
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $this->expectException(InvalidBackgroundSelectionException::class);

        $service->updateSelection($user, 'does-not-exist', []);
    }

    public function test_a_config_shape_that_does_not_match_the_resolved_type_is_rejected(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $this->expectException(InvalidBackgroundSelectionException::class);

        // Gradient-shaped config submitted against a flat_color option.
        $service->updateSelection($user, 'flat_color', ['from' => '#112233', 'to' => '#445566']);
    }

    public function test_clear_selection_resets_both_columns_to_null(): void
    {
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->withWorkspaceBackground($option, ['color' => '#aabbcc'])->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->clearSelection($user);

        $this->assertNull($updated->workspace_background_option_id);
        $this->assertNull($updated->workspace_background_config);
    }

    public function test_available_options_for_includes_enabled_options_only_by_default(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color', 'sort_order' => 0]);
        WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'gradient', 'type' => 'gradient', 'sort_order' => 1]);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $options = $service->availableOptionsFor($user);

        $this->assertSame(['flat_color'], $options->pluck('key')->all());
    }

    public function test_available_options_for_still_includes_the_users_own_disabled_selection(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color', 'sort_order' => 0]);
        $disabledGradient = WorkspaceBackgroundOption::factory()
            ->disabled()
            ->create(['key' => 'gradient', 'type' => 'gradient', 'sort_order' => 1]);
        $user = User::factory()
            ->withWorkspaceBackground($disabledGradient, ['from' => '#112233', 'to' => '#445566'])
            ->create();
        $service = app(WorkspaceBackgroundService::class);

        $options = $service->availableOptionsFor($user);

        $this->assertEqualsCanonicalizing(['flat_color', 'gradient'], $options->pluck('key')->all());
    }

    public function test_resolved_background_for_returns_null_when_no_preference_is_set(): void
    {
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $this->assertNull($service->resolvedBackgroundFor($user));
    }

    public function test_resolved_background_for_turns_a_stored_image_path_into_a_public_url(): void
    {
        Storage::fake('public');
        $option = WorkspaceBackgroundOption::factory()->create(['key' => 'image', 'type' => 'image']);
        $user = User::factory()
            ->withWorkspaceBackground($option, ['path' => 'workspace-backgrounds/example.jpg'])
            ->create();
        $service = app(WorkspaceBackgroundService::class);

        $resolved = $service->resolvedBackgroundFor($user);

        $this->assertNotNull($resolved);
        $this->assertSame('image', $resolved->type);
        $this->assertStringEndsWith('workspace-backgrounds/example.jpg', $resolved->config['url']);
    }

    public function test_selecting_a_flat_color_preset_with_an_empty_config_adopts_its_default(): void
    {
        $option = WorkspaceBackgroundOption::factory()
            ->withDefaultConfig(['color' => '#8b6fd6'])
            ->create(['key' => 'flat_color_amethyst', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'flat_color_amethyst', []);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertNull($updated->workspace_background_config);

        $resolved = $service->resolvedBackgroundFor($updated);
        $this->assertNotNull($resolved);
        $this->assertSame(['color' => '#8b6fd6'], $resolved->config);
    }

    public function test_selecting_a_gradient_preset_with_an_empty_config_adopts_its_default(): void
    {
        $option = WorkspaceBackgroundOption::factory()
            ->withDefaultConfig(['from' => '#4a3f7a', 'to' => '#d98fb3'])
            ->create(['key' => 'gradient_twilight', 'type' => 'gradient']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'gradient_twilight', []);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertNull($updated->workspace_background_config);

        $resolved = $service->resolvedBackgroundFor($updated);
        $this->assertNotNull($resolved);
        $this->assertSame(['from' => '#4a3f7a', 'to' => '#d98fb3'], $resolved->config);
    }

    public function test_selecting_an_image_preset_with_an_empty_config_adopts_its_default_without_validation(): void
    {
        $option = WorkspaceBackgroundOption::factory()
            ->withDefaultConfig(['url' => '/images/workspace-backgrounds/aurora-waves.svg'])
            ->create(['key' => 'image_aurora_waves', 'type' => 'image']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'image_aurora_waves', []);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertNull($updated->workspace_background_config);

        $resolved = $service->resolvedBackgroundFor($updated);
        $this->assertNotNull($resolved);
        $this->assertSame(['url' => '/images/workspace-backgrounds/aurora-waves.svg'], $resolved->config);
    }

    public function test_selecting_an_option_with_an_empty_config_and_no_default_still_throws(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $this->expectException(InvalidBackgroundSelectionException::class);

        $service->updateSelection($user, 'flat_color', []);
    }

    public function test_adopting_a_preset_unmodified_tracks_later_default_config_edits_live(): void
    {
        $option = WorkspaceBackgroundOption::factory()
            ->withDefaultConfig(['color' => '#8b6fd6'])
            ->create(['key' => 'flat_color_amethyst', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'flat_color_amethyst', []);
        $this->assertNull($updated->workspace_background_config);

        $option->update(['default_config' => ['color' => '#112233']]);

        $resolved = $service->resolvedBackgroundFor($updated);

        $this->assertNotNull($resolved);
        $this->assertSame(['color' => '#112233'], $resolved->config);
    }

    public function test_a_submitted_config_overrides_the_options_default_config(): void
    {
        $option = WorkspaceBackgroundOption::factory()
            ->withDefaultConfig(['color' => '#8b6fd6'])
            ->create(['key' => 'flat_color_amethyst', 'type' => 'flat_color']);
        $user = User::factory()->create();
        $service = app(WorkspaceBackgroundService::class);

        $updated = $service->updateSelection($user, 'flat_color_amethyst', ['color' => '#112233']);

        $this->assertSame($option->id, $updated->workspace_background_option_id);
        $this->assertSame(['color' => '#112233'], $updated->workspace_background_config);

        // A later edit to the catalog's default_config must not affect a
        // user who customized their own value.
        $option->update(['default_config' => ['color' => '#000000']]);

        $resolved = $service->resolvedBackgroundFor($updated);

        $this->assertNotNull($resolved);
        $this->assertSame(['color' => '#112233'], $resolved->config);
    }
}
