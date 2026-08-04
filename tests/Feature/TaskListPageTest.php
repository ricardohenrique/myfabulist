<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_the_list_name_and_its_task_titles(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Website launch']);
        Task::factory()->forTaskList($list)->create(['title' => 'Ship the launch email']);

        $response = $this->actingAs($user)->get(route('lists.show', $list));

        $response->assertOk();
        $response->assertSee('Website launch');
        $response->assertSee('Ship the launch email');
    }

    public function test_another_users_list_is_forbidden(): void
    {
        $user = User::factory()->create();
        $foreignList = TaskList::factory()->create();

        $response = $this->actingAs($user)->get(route('lists.show', $foreignList));

        $response->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $list = TaskList::factory()->create();

        $response = $this->get(route('lists.show', $list));

        $response->assertRedirect(route('login'));
    }

    public function test_the_inboxs_id_redirects_to_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = app(TaskListService::class)->inboxFor($user);

        $response = $this->actingAs($user)->get(route('lists.show', $inbox));

        $response->assertRedirect(route('inbox'));
    }

    public function test_a_non_numeric_list_segment_does_not_match_the_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/lists/abc');

        $response->assertNotFound();
    }
}
