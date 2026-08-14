<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;
use App\Models\User;

/**
 * Defence-in-depth for two ownership-only Service operations, mirroring how
 * `TaskListService::delete()` re-checks its own `is_default` guard even
 * though `TaskListPolicy` already gates it, and how
 * `EloquentTaskListRepository::update()` re-checks membership status rather
 * than trusting the caller alone. Either `TaskListPolicy::manageMembers()`
 * or `TaskListPolicy::delete()` is still the real authorization boundary
 * (403 at the HTTP layer) for the two static constructors below — this is
 * the Service-level invariant behind them, rendered as a 422 like every
 * other `DomainException` in this family, not a second, inconsistent 403
 * path:
 *
 * - `::forManageMembers()` — `ListSharingService::revoke()`: only the owner
 *   may remove another member.
 * - `::forDelete()` — `TaskListService::delete()` (F10/F12, Plan 1, Step 6):
 *   a non-owner accepted member must never be able to delete a shared list
 *   outright, which would soft-delete it for every member, not just
 *   themselves.
 */
class NotListOwnerException extends DomainException
{
    public static function forManageMembers(TaskList $taskList, User $actor): self
    {
        return new self(sprintf(
            'User [%d] is not the owner of list "%s" and cannot manage its members.',
            $actor->id,
            $taskList->name,
        ));
    }

    public static function forDelete(TaskList $taskList, User $actor): self
    {
        return new self(sprintf(
            'User [%d] is not the owner of list "%s" and cannot delete it.',
            $actor->id,
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'not_list_owner';
    }
}
