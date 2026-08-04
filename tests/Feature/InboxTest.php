<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('inbox'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_inbox(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('inbox'));
        $response->assertOk();
        $response->assertSee('Inbox');
    }

    public function test_the_controller_resolves_a_real_inbox_for_the_user(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $user->taskLists()->where('is_default', true)->count());

        $this->actingAs($user)->get(route('inbox'))->assertOk();

        $this->assertSame(1, $user->taskLists()->where('is_default', true)->count());
    }

    public function test_the_page_renders_seeded_task_titles(): void
    {
        $user = User::factory()->create();
        $inbox = TaskList::factory()->inbox()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($inbox)->create(['title' => 'Pick up dry cleaning']);

        $response = $this->actingAs($user)->get(route('inbox'));

        $response->assertOk();
        $response->assertSee('Pick up dry cleaning');
    }
}
