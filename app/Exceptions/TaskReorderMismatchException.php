<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Thrown when a bulk reorder submits an id set that does not exactly match
 * the target scope's current ids (missing, extra, or foreign ids). Nothing
 * is written when this is thrown (D12 — the repository wraps the write in a
 * transaction and validates before touching any row).
 */
class TaskReorderMismatchException extends DomainException
{
    public static function for(TaskList $taskList): self
    {
        return new self(sprintf(
            'The submitted task order does not match the current tasks in list "%s".',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'task_reorder_mismatch';
    }
}
