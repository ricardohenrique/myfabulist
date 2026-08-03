<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarredTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('starred'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_starred(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('starred'));
        $response->assertOk();
        $response->assertSee('Starred');
    }
}
