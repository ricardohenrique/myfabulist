<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a task title is blank or whitespace-only after trimming (M3).
 * This is the invariant that must hold regardless of which delivery
 * mechanism calls TaskService::create() / TaskService::rename().
 */
class InvalidTaskTitleException extends DomainException
{
    public static function becauseBlank(): self
    {
        return new self('A task title cannot be blank.');
    }

    public function errorCode(): string
    {
        return 'invalid_task_title';
    }
}
