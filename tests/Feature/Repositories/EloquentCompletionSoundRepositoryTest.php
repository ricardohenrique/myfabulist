<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\CompletionSound;
use App\Repositories\Contracts\CompletionSoundRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentCompletionSoundRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CompletionSoundRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        CompletionSound::query()->delete();
        $this->repository = app(CompletionSoundRepositoryInterface::class);
    }

    public function test_enabled_sounds_are_ordered_and_disabled_sounds_are_hidden(): void
    {
        CompletionSound::factory()->create(['key' => 'later', 'sort_order' => 2]);
        CompletionSound::factory()->create(['key' => 'first', 'sort_order' => 0]);
        CompletionSound::factory()->disabled()->create(['key' => 'hidden', 'sort_order' => 1]);

        $this->assertSame(['first', 'later'], $this->repository->enabled()->pluck('key')->all());
    }

    public function test_sounds_can_be_resolved_by_key_or_id_regardless_of_enabled_state(): void
    {
        $sound = CompletionSound::factory()->disabled()->create(['key' => 'existing']);

        $this->assertTrue($this->repository->findByKey('existing')?->is($sound) ?? false);
        $this->assertTrue($this->repository->findById($sound->id)?->is($sound) ?? false);
    }

    public function test_default_returns_the_flagged_sound(): void
    {
        CompletionSound::factory()->create();
        $default = CompletionSound::factory()->asDefault()->create();

        $this->assertTrue($this->repository->default()?->is($default) ?? false);
    }
}
