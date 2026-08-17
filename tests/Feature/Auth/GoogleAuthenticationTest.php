<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Database\Seeders\WorkspaceBackgroundOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_can_register_with_a_verified_google_account(): void
    {
        $this->seed(WorkspaceBackgroundOptionSeeder::class);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticated();

        $user = User::query()->where('google_id', 'google-123')->firstOrFail();

        $this->assertSame('person@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertSame('https://example.com/avatar.jpg', $user->profile_photo_url);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('task_lists', [
            'user_id' => $user->id,
            'name' => 'Inbox',
            'is_default' => true,
        ]);
        $this->assertSame(
            WorkspaceBackgroundOption::query()->where('is_default', true)->value('id'),
            $user->workspace_background_option_id,
        );
    }

    public function test_verified_google_email_links_an_existing_password_account(): void
    {
        $user = User::factory()->create([
            'email' => 'Person@Example.com',
            'email_verified_at' => null,
            'password' => 'password',
        ]);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(1, User::query()->count());
    }

    public function test_existing_google_account_is_reused_without_matching_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old-address@example.com',
            'google_id' => 'google-123',
            'avatar' => 'https://example.com/old-avatar.jpg',
        ]);
        $this->mockGoogleUser($this->googleUser(email: 'new-address@example.com'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('old-address@example.com', $user->fresh()->email);
        $this->assertSame('https://example.com/avatar.jpg', $user->fresh()->avatar);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->mockGoogleUser($this->googleUser(verified: false));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google did not provide a verified email address. Please use another sign-in method.');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_google_account_cannot_replace_a_different_existing_link(): void
    {
        User::factory()->create([
            'email' => 'person@example.com',
            'google_id' => 'different-google-id',
        ]);
        $this->mockGoogleUser($this->googleUser());

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This Purplelist account is already linked to another Google account.');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'person@example.com',
            'google_id' => 'different-google-id',
        ]);
    }

    public function test_google_provider_failure_returns_to_login_with_an_error(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('OAuth failed'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Unable to sign in with Google. Please try again.');

        $this->assertGuest();
    }

    private function mockGoogleUser(GoogleUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    private function googleUser(
        bool $verified = true,
        string $email = 'person@example.com',
    ): GoogleUser {
        return (new GoogleUser)
            ->setRaw(['email_verified' => $verified])
            ->map([
                'id' => 'google-123',
                'name' => 'Google Person',
                'email' => $email,
                'avatar' => 'https://example.com/avatar.jpg',
            ]);
    }
}
