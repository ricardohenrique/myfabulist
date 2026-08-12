<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;

/**
 * Thrown when a list already has `config('sharing.max_members_per_list')`
 * accepted members (Q10(c): 10, owner + 9 collaborators) — whether the cap
 * is hit at invite time or re-hit at accept time (N5: preconditions are
 * re-checked, not just checked once at invite time).
 */
class TaskListMemberLimitReachedException extends DomainException
{
    public static function for(TaskList $taskList): self
    {
        return new self(sprintf(
            'List "%s" already has the maximum of %d members.',
            $taskList->name,
            config('sharing.max_members_per_list'),
        ));
    }

    public function errorCode(): string
    {
        return 'task_list_member_limit_reached';
    }
}
