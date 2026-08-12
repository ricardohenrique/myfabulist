<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(UserRepositoryInterface::class);
    }

    public function test_find_by_email_returns_the_matching_user(): void
    {
        $user = User::factory()->create(['email' => 'collaborator@example.com']);

        $found = $this->repository->findByEmail('collaborator@example.com');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($user));
    }

    public function test_find_by_email_returns_null_when_no_user_matches(): void
    {
        $this->assertNull($this->repository->findByEmail('nobody@example.com'));
    }

    /**
     * The repository folds case in the query itself (`lower(email) = ?`)
     * rather than trusting the caller to have already normalized the input
     * — see the Eloquent implementation's docblock for why relying on both
     * sides independently agreeing on lowercase is not sufficient.
     * `App\Actions\Fortify\CreateNewUser` does not lowercase on
     * registration, so a user can genuinely be stored with mixed case; a
     * lookup for that same address in any case must still find it.
     */
    public function test_find_by_email_matches_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'Mixed@Example.com']);

        $this->assertTrue($this->repository->findByEmail('mixed@example.com')?->is($user));
        $this->assertTrue($this->repository->findByEmail('MIXED@EXAMPLE.COM')?->is($user));
        $this->assertTrue($this->repository->findByEmail('Mixed@Example.com')?->is($user));
    }
}
