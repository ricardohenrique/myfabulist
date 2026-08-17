<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_public(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/privacy'));
    }

    public function test_terms_page_is_public(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/terms'));
    }

    public function test_authenticated_users_can_view_legal_pages(): void
    {
        $user = User::factory()->make();

        $this->actingAs($user)->get(route('privacy'))->assertOk();
        $this->actingAs($user)->get(route('terms'))->assertOk();
    }
}
