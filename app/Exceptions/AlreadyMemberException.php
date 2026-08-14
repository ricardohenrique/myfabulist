<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;
use App\Models\User;

/**
 * Thrown when inviting a user who already has an *accepted* membership on
 * the list. A pending invite for the same user is not this case — it is
 * idempotent (see `ListSharingService::invite()`).
 */
class AlreadyMemberException extends DomainException
{
    public static function for(TaskList $taskList, User $user): self
    {
        return new self(sprintf(
            'User [%d] is already a member of list "%s".',
            $user->id,
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'already_member';
    }
}
