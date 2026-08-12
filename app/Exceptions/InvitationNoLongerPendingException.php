<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TaskList;
use App\Models\User;

/**
 * Thrown by `accept()`/`decline()` when the membership row exists and
 * belongs to the caller, but is no longer `pending` — `accept()` hits this
 * for a `declined` row, `decline()` hits this for an `accepted` row. Kept
 * distinct from `NotAMemberException` (which means "no relevant row at
 * all") on purpose: an API client needs to be able to tell "you were never
 * invited" apart from "this invitation already has a final answer and needs
 * a fresh invite" — collapsing both into one error code would make that
 * distinction impossible once Step 7 pins these as a stable contract.
 */
class InvitationNoLongerPendingException extends DomainException
{
    public static function for(TaskList $taskList, User $user): self
    {
        return new self(sprintf(
            'The invitation for user [%d] to list "%s" is no longer pending.',
            $user->id,
            $taskList->name,
        ));
    }

    public function errorCode(): string
    {
        return 'invitation_no_longer_pending';
    }
}
