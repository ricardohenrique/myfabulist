<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * The owner's membership row is structurally protected from removal, either
 * by the owner leaving voluntarily or by an explicit revoke call — both are
 * the same underlying invariant (there is no ownership-transfer mechanism in
 * v1, so the owner's only way out is `TaskListService::delete()`, which
 * removes the list for every member, Q6). One exception, two static
 * constructors so the message reads correctly for either caller. Named for
 * the shared invariant ("the owner's membership cannot be removed") rather
 * than either individual verb — "OwnerCannotLeave..." read wrong at the
 * revoke() call site, since nobody is "leaving" when they're being revoked.
 */
class OwnerMembershipCannotBeRemovedException extends DomainException
{
    public static function becauseTheyAreLeaving(TaskList $taskList): self
    {
        return new self(sprintf(
            'The owner of list "%s" cannot leave it. Delete the list instead.',
            $taskList->name,
        ));
    }

    public static function becauseTheyAreBeingRevoked(TaskList $taskList): self
    {
        return new self(sprintf(
            'The owner of list "%s" cannot be revoked. Delete the list instead.',
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'owner_membership_cannot_be_removed';
    }
}
