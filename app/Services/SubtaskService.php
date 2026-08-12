<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidSubtaskTitleException;
use App\Models\Subtask;
use App\Models\Task;
use App\Repositories\Contracts\SubtaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SubtaskService
{
    private const MAX_TITLE_LENGTH = 150;

    public function __construct(
        private readonly SubtaskRepositoryInterface $subtasks,
    ) {}

    /**
     * @return Collection<int, Subtask>
     */
    public function forTask(Task $task): Collection
    {
        return $this->subtasks->forTask($task);
    }

    public function create(Task $task, string $title): Subtask
    {
        return $this->subtasks->create($task, $this->requireValidTitle($title));
    }

    public function rename(Subtask $subtask, string $title): Subtask
    {
        return $this->subtasks->rename($subtask, $this->requireValidTitle($title));
    }

    /**
     * Idempotent: completing an already-completed subtask is a no-op.
     */
    public function complete(Subtask $subtask): Subtask
    {
        return $this->subtasks->markCompleted($subtask);
    }

    /**
     * Idempotent: restoring (un-checking) an already-active subtask is a
     * no-op.
     */
    public function restore(Subtask $subtask): Subtask
    {
        return $this->subtasks->markActive($subtask);
    }

    public function delete(Subtask $subtask): void
    {
        $this->subtasks->delete($subtask);
    }

    private function requireValidTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw InvalidSubtaskTitleException::becauseBlank();
        }

        if (Str::length($title) > self::MAX_TITLE_LENGTH) {
            throw InvalidSubtaskTitleException::becauseTooLong();
        }

        return $title;
    }
}
