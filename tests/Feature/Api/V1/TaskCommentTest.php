<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_or_create_task_comments(): void
    {
        $task = Task::factory()->create();

        $this->getJson("/api/v1/tasks/{$task->id}/comments")->assertUnauthorized();
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Hello'])->assertUnauthorized();
    }

    public function test_owner_can_create_a_plain_text_comment_with_author_metadata(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/comments", [
            'body' => '  <strong>Ship it</strong>  ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', '<strong>Ship it</strong>')
            ->assertJsonPath('data.author.id', $user->id)
            ->assertJsonPath('data.author.name', 'Ada Lovelace')
            ->assertJsonPath('data.author.avatar_url', null);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => '<strong>Ship it</strong>',
        ]);
    }

    public function test_comments_are_returned_oldest_first_and_included_with_task_details(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();
        $newer = TaskComment::factory()->forTask($task, $user)->create([
            'body' => 'Second',
            'created_at' => now(),
        ]);
        $older = TaskComment::factory()->forTask($task, $user)->create([
            'body' => 'First',
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);

        $this->actingAs($user)->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $older->id)
            ->assertJsonPath('data.comments.0.author.id', $user->id);
    }

    public function test_blank_and_oversized_comments_are_rejected(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($user)->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => str_repeat('a', 65_536)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('task_comments', 0);
    }

    public function test_another_user_cannot_read_or_comment_on_a_task(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->forTaskList($list)->create();
        TaskComment::factory()->forTask($task, $owner)->create();

        $this->actingAs($stranger)->getJson("/api/v1/tasks/{$task->id}/comments")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Nope'])->assertForbidden();

        $this->assertDatabaseCount('task_comments', 1);
    }
}
