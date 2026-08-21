<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\InvalidCompletionSoundSelectionException;
use App\Models\CompletionSound;
use App\Models\User;
use App\Services\CompletionSoundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionSoundServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompletionSoundService $service;

    protected function setUp(): void
    {
        parent::setUp();

        CompletionSound::query()->delete();
        $this->service = app(CompletionSoundService::class);
    }

    public function test_user_can_select_and_resolve_a_sound(): void
    {
        $sound = CompletionSound::factory()->create([
            'key' => 'soft_chime',
            'label' => 'Soft chime',
            'file_path' => 'soft-chime.mp3',
        ]);
        $user = User::factory()->create();

        $updated = $this->service->updateSelection($user, 'soft_chime');
        $resolved = $this->service->resolvedSoundFor($updated);

        $this->assertSame($sound->id, $updated->completion_sound_id);
        $this->assertSame('soft_chime', $resolved?->key);
        $this->assertSame('/soft-chime.mp3', $resolved?->url);
    }

    public function test_null_selection_means_no_sound(): void
    {
        $sound = CompletionSound::factory()->create();
        $user = User::factory()->withCompletionSound($sound)->create();

        $updated = $this->service->updateSelection($user, null);

        $this->assertNull($updated->completion_sound_id);
        $this->assertNull($this->service->resolvedSoundFor($updated));
    }

    public function test_disabled_sound_cannot_be_newly_selected(): void
    {
        CompletionSound::factory()->disabled()->create(['key' => 'retired']);
        $user = User::factory()->create();

        $this->expectException(InvalidCompletionSoundSelectionException::class);

        $this->service->updateSelection($user, 'retired');
    }

    public function test_current_disabled_sound_stays_visible_and_can_be_kept(): void
    {
        $sound = CompletionSound::factory()->disabled()->create(['key' => 'retired']);
        $user = User::factory()->withCompletionSound($sound)->create();

        $this->assertTrue($this->service->availableOptionsFor($user)->contains('id', $sound->id));
        $this->assertSame($sound->id, $this->service->updateSelection($user, 'retired')->completion_sound_id);
    }

    public function test_default_is_assigned_without_overwriting_an_existing_choice(): void
    {
        $default = CompletionSound::factory()->asDefault()->create();
        $chosen = CompletionSound::factory()->create();
        $newUser = User::factory()->create();
        $existingUser = User::factory()->withCompletionSound($chosen)->create();

        $this->service->assignDefaultTo($newUser);
        $this->service->assignDefaultTo($existingUser);

        $this->assertSame($default->id, $newUser->fresh()->completion_sound_id);
        $this->assertSame($chosen->id, $existingUser->fresh()->completion_sound_id);
    }
}
