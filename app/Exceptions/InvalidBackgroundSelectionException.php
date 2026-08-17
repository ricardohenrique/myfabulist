<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by `WorkspaceBackgroundService::updateSelection()` for every way a
 * requested background selection can be invalid: the option key does not
 * exist, the option is currently disabled and is not already the user's own
 * selection, or the submitted config does not match what the option's type
 * requires. Form Request validation (`option_key` existence, per-field
 * shape) catches the easy cases before this is ever reached — this is the
 * business-rule layer's own defence, not a duplicate of it.
 */
class InvalidBackgroundSelectionException extends DomainException
{
    public static function becauseUnknownKey(string $optionKey): self
    {
        return new self(sprintf('Workspace background option "%s" does not exist.', $optionKey));
    }

    public static function becauseDisabled(string $optionKey): self
    {
        return new self(sprintf('Workspace background option "%s" is not currently available.', $optionKey));
    }

    public static function becauseInvalidConfig(string $type): self
    {
        return new self(sprintf('The submitted configuration is not valid for the "%s" background type.', $type));
    }

    public function errorCode(): string
    {
        return 'invalid_background_selection';
    }
}
