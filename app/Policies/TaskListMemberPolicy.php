<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskListMember;
use App\Models\User;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7 code-review follow-up:
 * a single-ability Policy for `TaskListMember`, auto-discovered like every
 * other Policy in this app. Added so "is this invitation mine to respond
 * to" has exactly one definition — `RespondToInvitationRequest` (API) calls
 * it, and Step 8's web equivalent will too, rather than each Form Request
 * carrying its own copy of the same inline check.
 */
class TaskListMemberPolicy
{
    /**
     * Only the invitee may accept or decline their own invitation.
     */
    public function respond(User $user, TaskListMember $membership): bool
    {
        return $membership->user_id === $user->id;
    }
}
