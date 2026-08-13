<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 6/Q8: a task can never
 * move into or out of a shared list, in either direction, regardless of who
 * initiates it — this applies uniformly to the list's owner and to a
 * non-owner member, not just to a member moving content out of a list they
 * do not own. `TaskService::move()`'s target resolution (`findOwnedBy()`)
 * already guarantees the mover owns *a* list on each side of the move; this
 * exception is the guard for the case ownership alone doesn't cover —
 * either side of the move being a list with more than one accepted member.
 */
class TaskCannotCrossSharingBoundaryException extends DomainException
{
    public static function becauseListIsShared(TaskList $taskList): self
    {
        return new self(sprintf(
            'Task cannot move across the sharing boundary: list "%s" is shared with other members.',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'task_cannot_cross_sharing_boundary';
    }
}
