<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

interface SubtaskRepositoryInterface
{
    /**
     * @return Collection<int, Subtask>
     */
    public function forTask(Task $task): Collection;

    public function create(Task $task, string $title): Subtask;

    public function rename(Subtask $subtask, string $title): Subtask;

    /**
     * The sole writer of `is_completed = true` (checkbox checked).
     * Idempotent: a no-op when already completed.
     */
    public function markCompleted(Subtask $subtask): Subtask;

    /**
     * The sole writer of `is_completed = false` (checkbox unchecked).
     * Idempotent: a no-op when already active.
     */
    public function markActive(Subtask $subtask): Subtask;

    public function delete(Subtask $subtask): void;
}
