<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Thrown when a caller attempts to invite someone to the Inbox (F9 — the
 * default list is never shareable, "permanent, ungrouped, and user-owned").
 * Enforced at the service layer (`ListSharingService::invite()`), not just
 * hidden in the UI.
 */
class TaskListCannotBeSharedException extends DomainException
{
    public static function becauseItIsTheDefaultList(TaskList $taskList): self
    {
        return new self(sprintf(
            'List "%s" is the default Inbox list and cannot be shared.',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'task_list_cannot_be_shared';
    }
}
