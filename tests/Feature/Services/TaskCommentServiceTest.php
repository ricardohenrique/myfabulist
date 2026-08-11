<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\InvalidTaskCommentException;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_trims_and_persists_the_author(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $comment = app(TaskCommentService::class)->create($task, $user, '  Ready to ship  ');

        $this->assertSame('Ready to ship', $comment->body);
        $this->assertTrue($user->is($comment->author));
    }

    public function test_create_rejects_blank_content_at_the_domain_boundary(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->expectException(InvalidTaskCommentException::class);

        app(TaskCommentService::class)->create($task, $user, '   ');
    }

    public function test_create_rejects_content_beyond_the_text_limit(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->forTaskList($list)->create();

        $this->expectException(InvalidTaskCommentException::class);

        app(TaskCommentService::class)->create($task, $user, str_repeat('a', 65_536));
    }
}
