<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;
use App\Models\User;

/**
 * Defence-in-depth for `ListSharingService::revoke()`, mirroring how
 * `TaskListService::delete()` re-checks its own `is_default` guard even
 * though `TaskListPolicy` already gates it, and how
 * `EloquentTaskListRepository::update()` re-checks membership status rather
 * than trusting the caller alone. `TaskListPolicy::manageMembers()` is still
 * the real authorization boundary (403 at the HTTP layer) — this is the
 * Service-level invariant behind it, rendered as a 422 like every other
 * `DomainException` in this family, not a second, inconsistent 403 path.
 */
class NotListOwnerException extends DomainException
{
    public static function for(TaskList $taskList, User $actor): self
    {
        return new self(sprintf(
            'User [%d] is not the owner of list "%s" and cannot manage its members.',
            $actor->id,
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'not_list_owner';
    }
}
