<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;

/**
 * Thrown when the inviter's own email is submitted as the invitee.
 */
class CannotInviteSelfException extends DomainException
{
    public static function for(User $user): self
    {
        return new self(sprintf(
            'User [%d] cannot invite themselves to a list.',
            $user->id,
        ));
    }

    public function errorCode(): string
    {
        return 'cannot_invite_self';
    }
}
