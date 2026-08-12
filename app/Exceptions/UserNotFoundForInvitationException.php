<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when an invitee email matches no registered user (Q3: strictly
 * registered-users-only, no pending-signup flow, no token-based invite).
 */
class UserNotFoundForInvitationException extends DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf(
            'No registered user was found for email "%s".',
            $email,
        ));
    }

    public function errorCode(): string
    {
        return 'user_not_found_for_invitation';
    }
}
