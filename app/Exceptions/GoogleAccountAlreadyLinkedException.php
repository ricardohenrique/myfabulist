<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class GoogleAccountAlreadyLinkedException extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self("The account for {$email} is already linked to a different Google account.");
    }
}
