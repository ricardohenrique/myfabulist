<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\InvalidSubtaskTitleException;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Services\SubtaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_trims_and_persists_the_title(): void
    {
        $task = Task::factory()->create();

        $subtask = app(SubtaskService::class)->create($task, '  Buy stamps  ');

        $this->assertSame('Buy stamps', $subtask->title);
        $this->assertFalse($subtask->is_completed);
        $this->assertTrue($task->is($subtask->task));
    }

    public function test_create_rejects_blank_title_at_the_domain_boundary(): void
    {
        $task = Task::factory()->create();

        $this->expectException(InvalidSubtaskTitleException::class);

        app(SubtaskService::class)->create($task, '   ');
    }

    public function test_create_rejects_title_beyond_150_characters(): void
    {
        $task = Task::factory()->create();

        $this->expectException(InvalidSubtaskTitleException::class);

        app(SubtaskService::class)->create($task, str_repeat('a', 151));
    }

    public function test_create_accepts_a_title_at_exactly_150_characters(): void
    {
        $task = Task::factory()->create();
        $title = str_repeat('a', 150);

        $subtask = app(SubtaskService::class)->create($task, $title);

        $this->assertSame($title, $subtask->title);
    }

    public function test_rename_trims_and_validates_like_create(): void
    {
        $subtask = Subtask::factory()->create(['title' => 'Original']);

        $renamed = app(SubtaskService::class)->rename($subtask, '  Renamed  ');

        $this->assertSame('Renamed', $renamed->title);

        $this->expectException(InvalidSubtaskTitleException::class);

        app(SubtaskService::class)->rename($subtask, '  ');
    }

    public function test_complete_and_restore_are_idempotent(): void
    {
        $subtask = Subtask::factory()->create(['is_completed' => false]);
        $service = app(SubtaskService::class);

        $completed = $service->complete($subtask);
        $this->assertTrue($completed->is_completed);

        $completedAgain = $service->complete($completed);
        $this->assertTrue($completedAgain->is_completed);

        $restored = $service->restore($completedAgain);
        $this->assertFalse($restored->is_completed);

        $restoredAgain = $service->restore($restored);
        $this->assertFalse($restoredAgain->is_completed);
    }

    public function test_delete_removes_the_subtask(): void
    {
        $subtask = Subtask::factory()->create();

        app(SubtaskService::class)->delete($subtask);

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
    }

    public function test_for_task_returns_only_that_tasks_subtasks_oldest_first(): void
    {
        $list = TaskList::factory()->create();
        $task = Task::factory()->forTaskList($list)->create();
        $otherTask = Task::factory()->forTaskList($list)->create();

        $first = Subtask::factory()->forTask($task)->create(['created_at' => now()->subMinute()]);
        $second = Subtask::factory()->forTask($task)->create(['created_at' => now()]);
        Subtask::factory()->forTask($otherTask)->create();

        $result = app(SubtaskService::class)->forTask($task);

        $this->assertCount(2, $result);
        $this->assertSame($first->id, $result->first()->id);
        $this->assertSame($second->id, $result->last()->id);
    }
}
