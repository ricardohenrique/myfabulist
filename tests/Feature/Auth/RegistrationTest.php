<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use Database\Seeders\WorkspaceBackgroundOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('task_lists', [
            'name' => 'Inbox',
            'is_default' => true,
        ]);
    }

    public function test_new_users_start_on_the_platform_default_workspace_background(): void
    {
        $this->seed(WorkspaceBackgroundOptionSeeder::class);
        $twilight = WorkspaceBackgroundOption::query()->where('key', 'gradient_twilight')->firstOrFail();

        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertSame($twilight->id, $user->workspace_background_option_id);
        $this->assertNull($user->workspace_background_config);
    }
}
