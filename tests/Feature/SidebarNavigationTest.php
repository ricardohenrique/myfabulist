<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_inbox_and_starred_links_and_no_legacy_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee(route('inbox'), false);
        $response->assertSee(route('starred'), false);
        $response->assertDontSee('Dashboard');
        $response->assertDontSee('Platform');
    }

    public function test_starred_page_marks_the_starred_nav_item_as_current(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('starred'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'href="'.route('starred').'"',
            'data-current="data-current"',
        ], false);
    }

    public function test_user_without_a_photo_renders_initials_and_no_image(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'profile_photo_path' => null]);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee($user->initials());
        $response->assertDontSee('<img', false);
    }

    public function test_user_with_a_photo_renders_an_image_with_the_stored_path(): void
    {
        $user = User::factory()->withProfilePhoto('profile-photos/avatar.jpg')->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee('<img', false);
        $response->assertSee('profile-photos/avatar.jpg', false);
    }

    public function test_identity_block_precedes_the_primary_nav(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-test="sidebar-menu-button"',
            'data-nav="primary"',
        ], false);
    }
}
