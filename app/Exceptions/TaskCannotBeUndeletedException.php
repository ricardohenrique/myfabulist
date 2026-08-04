<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Task;

/**
 * Thrown when un-deleting a task would leave it invisible — its parent list
 * is itself deleted (D6/Plan 4). Un-delete must refuse loudly here rather
 * than report success and hide the task somewhere the user can't reach it.
 */
class TaskCannotBeUndeletedException extends DomainException
{
    public static function becauseItsListIsDeleted(Task $task): self
    {
        return new self(sprintf(
            'Task "%s" cannot be undeleted because its list has been deleted.',
            $task->title,
        ));
    }

    public function errorCode(): string
    {
        return 'task_cannot_be_undeleted';
    }
}
