<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

/**
 * The M4/M6 task read model for a single list: active tasks (ordered by
 * position, with new tasks inserted first), completed tasks (most recently completed first), and a
 * completed count. Shared by the Inertia presenter and API list-task
 * endpoints so both transports use the same ordering rules.
 */
final readonly class ListedTasks
{
    /**
     * @param  Collection<int, Task>  $active
     * @param  Collection<int, Task>  $completed
     */
    public function __construct(
        public Collection $active,
        public Collection $completed,
        public int $completedCount,
    ) {}
}
