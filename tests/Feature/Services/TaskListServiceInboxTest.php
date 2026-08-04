<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TaskListServiceInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_for_creates_the_default_list_once_and_returns_the_same_row_on_the_second_call(): void
    {
        $user = User::factory()->create();
        $service = app(TaskListService::class);

        $first = $service->inboxFor($user);
        $second = $service->inboxFor($user);

        $this->assertTrue($first->is($second));
        $this->assertTrue($first->is_default);
        $this->assertNull($first->folder_id);
        $this->assertSame(1, $user->taskLists()->where('is_default', true)->count());
    }

    public function test_two_users_get_separate_inboxes(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $service = app(TaskListService::class);

        $inboxA = $service->inboxFor($userA);
        $inboxB = $service->inboxFor($userB);

        $this->assertNotSame($inboxA->id, $inboxB->id);
        $this->assertSame($userA->id, $inboxA->user_id);
        $this->assertSame($userB->id, $inboxB->user_id);
    }

    public function test_a_user_created_via_the_registration_flow_already_has_an_inbox(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'inbox-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'inbox-test@example.com')->firstOrFail();

        $this->assertSame(1, $user->taskLists()->where('is_default', true)->count());
    }
}
