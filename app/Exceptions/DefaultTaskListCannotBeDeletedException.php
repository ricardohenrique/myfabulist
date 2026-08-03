<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Thrown when the caller attempts to delete or fold the Inbox (the default
 * task list) into a folder (D5).
 */
class DefaultTaskListCannotBeDeletedException extends DomainException
{
    public static function for(TaskList $taskList): self
    {
        return new self(sprintf(
            'List "%s" is the default Inbox list and cannot be deleted.',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'default_task_list_cannot_be_deleted';
    }
}
