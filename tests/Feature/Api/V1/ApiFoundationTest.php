<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_a_json_401_not_an_html_redirect(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_an_authenticated_verified_user_can_reach_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
        $response->assertJsonPath('email', $user->email);
    }

    public function test_an_unverified_user_is_forbidden(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user');

        $response->assertStatus(403);
    }

    public function test_every_api_v1_route_requires_sanctum_authentication(): void
    {
        $apiV1Routes = collect(Route::getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1'));

        $this->assertGreaterThan(0, $apiV1Routes->count(), 'Expected at least one api/v1 route to exist.');

        $apiV1Routes->each(function (RoutingRoute $route) {
            $this->assertContains(
                'auth:sanctum',
                $route->gatherMiddleware(),
                "Route [{$route->uri()}] does not carry the auth:sanctum middleware.",
            );
        });
    }

    public function test_the_api_rate_limiter_is_registered_and_headers_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
    }
}
