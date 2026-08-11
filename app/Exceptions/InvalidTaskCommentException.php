<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidTaskCommentException extends DomainException
{
    public static function becauseBlank(): self
    {
        return new self('A task comment cannot be blank.');
    }

    public static function becauseTooLong(): self
    {
        return new self('A task comment cannot be longer than 65,535 characters.');
    }

    public function errorCode(): string
    {
        return 'invalid_task_comment';
    }
}
