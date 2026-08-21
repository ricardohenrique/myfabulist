<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidCompletionSoundSelectionException extends DomainException
{
    public static function becauseUnknownKey(string $key): self
    {
        return new self(sprintf('Completion sound "%s" does not exist.', $key));
    }

    public static function becauseDisabled(string $key): self
    {
        return new self(sprintf('Completion sound "%s" is not currently available.', $key));
    }

    public function errorCode(): string
    {
        return 'invalid_completion_sound_selection';
    }
}
