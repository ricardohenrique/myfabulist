<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;
use App\Models\User;

/**
 * Thrown whenever an operation targets a user who has no relevant standing
 * on the list: accepting or declining someone else's invitation, or
 * revoking/leaving with no membership row at all.
 */
class NotAMemberException extends DomainException
{
    public static function for(TaskList $taskList, User $user): self
    {
        return new self(sprintf(
            'User [%d] is not a member of list "%s".',
            $user->id,
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'not_a_member';
    }
}
