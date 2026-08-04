<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarredTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('starred'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_starred(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('starred'));
        $response->assertOk();
        $response->assertSee('Starred');
    }

    public function test_the_page_renders_a_starred_tasks_title(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        Task::factory()->forTaskList($list)->starred()->create(['title' => 'Renew passport']);

        $response = $this->actingAs($user)->get(route('starred'));

        $response->assertOk();
        $response->assertSee('Renew passport');
    }

    public function test_the_empty_state_renders_for_a_user_with_no_starred_tasks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('starred'));

        $response->assertOk();
        $response->assertSee('No starred tasks yet.');
    }
}
