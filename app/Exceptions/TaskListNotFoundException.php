<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a referenced task list id does not exist or does not belong
 * to the acting user (D3) — the TaskService::move() analogue of
 * FolderNotFoundException, guaranteeing D7's "a task can only move between
 * lists belonging to the same user" invariant regardless of caller.
 */
class TaskListNotFoundException extends DomainException
{
    public static function forId(int $taskListId): self
    {
        return new self(sprintf('List [%d] was not found.', $taskListId));
    }

    public function errorCode(): string
    {
        return 'task_list_not_found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
