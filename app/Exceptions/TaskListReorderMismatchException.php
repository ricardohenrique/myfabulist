<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskListReorderMismatchException extends DomainException
{
    public static function forCurrentContainer(): self
    {
        return new self('The submitted list order no longer matches the current collection. Refresh and try again.');
    }

    public function errorCode(): string
    {
        return 'task_list_reorder_mismatch';
    }
}
