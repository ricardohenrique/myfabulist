<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_routes_require_authentication(): void
    {
        $this->patch(route('profile.update'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => '  Grace Hopper  ',
                'email' => '  grace@example.com  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Grace Hopper', $user->name);
        $this->assertSame('grace@example.com', $user->email);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->from(route('inbox'))
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => $otherUser->email,
            ])
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('inbox'));
    }

    public function test_user_can_update_their_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_current_password_is_required_to_update_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->from(route('inbox'))
            ->put(route('profile.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('inbox'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_google_only_user_can_set_their_first_password(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-123',
            'password' => null,
        ]);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => '',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }
}
