<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a subtask title is blank/whitespace-only after trimming, or
 * exceeds the 150-character limit. This is the invariant that must hold
 * regardless of which delivery mechanism calls SubtaskService::create() /
 * SubtaskService::rename().
 */
class InvalidSubtaskTitleException extends DomainException
{
    public static function becauseBlank(): self
    {
        return new self('A subtask title cannot be blank.');
    }

    public static function becauseTooLong(): self
    {
        return new self('A subtask title cannot be longer than 150 characters.');
    }

    public function errorCode(): string
    {
        return 'invalid_subtask_title';
    }
}
