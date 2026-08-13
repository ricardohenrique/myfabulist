<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Thrown when a bulk reorder submits an id set that does not exactly match
 * the target scope's current ids (missing, extra, or foreign ids). Nothing
 * is written when this is thrown (D12 — the repository wraps the write in a
 * transaction and validates before touching any row).
 *
 * F16 (Plan 1, Step 6): with sharing live, this is now a routine
 * collaboration event, not just staleness from an idle tab — another
 * accepted member of the list may have reordered it since it was loaded.
 * `errorCode()` is unchanged so nothing an API client branches on shifts;
 * only the human-readable message is reworded.
 */
class TaskReorderMismatchException extends DomainException
{
    public static function for(TaskList $taskList): self
    {
        return new self(sprintf(
            'The task order changed since you last loaded list "%s" — another member may have reordered it. Refresh and try again.',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'task_reorder_mismatch';
    }
}
