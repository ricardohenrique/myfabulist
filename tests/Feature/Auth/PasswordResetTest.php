<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
    }

    public function test_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password']);
        $token = '';

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/reset-password')
                ->where('email', $user->email)
                ->has('passwordRequirements', 2)
                ->where('passwordRequirements.letters', true)
                ->where('passwordRequirements.numbers', true)
                ->where('token', $token));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'r2',
            'password_confirmation' => 'r2',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('r2', $user->fresh()->password));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'r2',
        ])->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_google_only_user_can_request_a_reset_and_set_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['google_id' => 'google-123', 'password' => null]);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
