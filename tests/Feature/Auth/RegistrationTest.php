<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use App\Notifications\WelcomeVerifyEmailNotification;
use Database\Seeders\WorkspaceBackgroundOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('passwordRequirements.min', 8)
                ->where('passwordRequirements.mixedCase', true)
                ->where('passwordRequirements.letters', true)
                ->where('passwordRequirements.numbers', true)
                ->where('passwordRequirements.symbols', true));
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('task_lists', [
            'name' => 'Inbox',
            'is_default' => true,
        ]);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $inbox = $user->taskLists()->where('is_default', true)->sole();

        $this->assertSame([
            'Add something you need to do',
            "Check this task when you're finished",
        ], $inbox->tasks()->orderBy('position')->pluck('title')->all());
        $this->assertNull($user->onboarding_use_case);
        $this->assertNull($user->onboarding_completed_at);
        Notification::assertSentTo($user, WelcomeVerifyEmailNotification::class);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_registration_rejects_passwords_missing_the_required_character_types(): void
    {
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_new_users_start_on_the_platform_default_workspace_background(): void
    {
        $this->seed(WorkspaceBackgroundOptionSeeder::class);
        $twilight = WorkspaceBackgroundOption::query()->where('key', 'gradient_twilight')->firstOrFail();

        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertSame($twilight->id, $user->workspace_background_option_id);
        $this->assertNull($user->workspace_background_config);
    }
}
