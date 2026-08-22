<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\WelcomeVerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_still_use_the_application(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('inbox'))->assertOk();
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-email')
                ->where('email', $user->email));
    }

    public function test_email_can_be_verified_from_a_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('inbox'))
            ->assertSessionHas('success', 'Email confirmed. Thanks for verifying your address.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_confirmation_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_confirmation_email_links_request_a_new_browser_tab(): void
    {
        $user = User::factory()->unverified()->create();
        $welcomeHtml = (string) (new WelcomeVerifyEmailNotification)->toMail($user)->render();
        $confirmationHtml = (string) (new VerifyEmailNotification)->toMail($user)->render();
        $resetHtml = (string) (new ResetPasswordNotification('token'))->toMail($user)->render();

        $this->assertSame(2, substr_count($welcomeHtml, 'target="_blank"'));
        $this->assertSame(2, substr_count($confirmationHtml, 'target="_blank"'));
        $this->assertStringContainsString('rel="noopener noreferrer"', $welcomeHtml);
        $this->assertStringContainsString('rel="noopener noreferrer"', $confirmationHtml);
        $this->assertStringNotContainsString('target="_blank"', $resetHtml);
    }

    public function test_branded_email_templates_render_the_product_identity(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ada']);
        $welcomeHtml = (string) (new WelcomeVerifyEmailNotification)->toMail($user)->render();
        $resetHtml = (string) (new ResetPasswordNotification('token'))->toMail($user)->render();

        $this->assertStringContainsString('Purplelist', $welcomeHtml);
        $this->assertStringContainsString('#8b6fd6', $welcomeHtml);
        $this->assertStringContainsString('Confirm email address', $welcomeHtml);
        $this->assertStringContainsString('Reset my password', $resetHtml);
        $this->assertStringContainsString('Simple lists. Clear days.', $resetHtml);
    }
}
