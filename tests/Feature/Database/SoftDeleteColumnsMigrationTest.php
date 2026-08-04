<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Plan 4, Step 1: proves the additive `deleted_at` migration is inert — the
 * columns exist and default to null, but nothing reads or writes them yet
 * (the models gain SoftDeletes in Steps 2 and 4).
 */
class SoftDeleteColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_table_has_a_nullable_deleted_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('tasks', 'deleted_at'));

        $task = Task::factory()->create();

        $this->assertNull($task->deleted_at);
    }

    public function test_task_lists_table_has_a_nullable_deleted_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('task_lists', 'deleted_at'));

        $list = TaskList::factory()->create();

        $this->assertNull($list->deleted_at);
    }
}
