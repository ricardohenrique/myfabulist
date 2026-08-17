<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\WorkspaceBackgroundOption;
use App\Repositories\Contracts\WorkspaceBackgroundOptionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentWorkspaceBackgroundOptionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WorkspaceBackgroundOptionRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(WorkspaceBackgroundOptionRepositoryInterface::class);
    }

    public function test_enabled_excludes_disabled_options_and_orders_by_sort_order(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'gradient', 'sort_order' => 2]);
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color', 'sort_order' => 0]);
        WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'image', 'sort_order' => 1]);

        $options = $this->repository->enabled();

        $this->assertSame(['flat_color', 'gradient'], $options->pluck('key')->all());
    }

    public function test_find_by_key_returns_the_matching_option_regardless_of_enabled_state(): void
    {
        $option = WorkspaceBackgroundOption::factory()->disabled()->create(['key' => 'flat_color']);

        $found = $this->repository->findByKey('flat_color');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($option));
    }

    public function test_find_by_key_returns_null_when_no_option_matches(): void
    {
        $this->assertNull($this->repository->findByKey('does-not-exist'));
    }

    public function test_find_by_id_returns_the_matching_option_regardless_of_enabled_state(): void
    {
        $option = WorkspaceBackgroundOption::factory()->disabled()->create();

        $found = $this->repository->findById($option->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($option));
    }

    public function test_find_by_id_returns_null_when_no_option_matches(): void
    {
        $this->assertNull($this->repository->findById(999_999));
    }

    public function test_default_returns_the_option_flagged_as_default(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color']);
        $default = WorkspaceBackgroundOption::factory()->asDefault()->create(['key' => 'gradient']);

        $found = $this->repository->default();

        $this->assertNotNull($found);
        $this->assertTrue($found->is($default));
    }

    public function test_default_returns_null_when_no_option_is_flagged_as_default(): void
    {
        WorkspaceBackgroundOption::factory()->create(['key' => 'flat_color']);

        $this->assertNull($this->repository->default());
    }
}
