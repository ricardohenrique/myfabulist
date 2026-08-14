<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskListMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pending invitation, as rendered on `ListInvitationController::index()`
 * only — once responded to it is no longer meaningfully "an invitation", it
 * is the member's own membership record, rendered by `TaskListMemberResource`
 * instead. Requires `taskList` and `invitedBy` eager-loaded
 * (`TaskListMemberRepositoryInterface::pendingFor()` already does this).
 *
 * @mixin TaskListMember
 */
class ListInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list' => [
                'id' => $this->taskList->id,
                'name' => $this->taskList->name,
            ],
            // invited_by_user_id is nullable()->nullOnDelete() — unreachable
            // today (no account-deletion feature exists yet, and invite()
            // always stamps a real inviter), but Q7 already flags account
            // deletion as a future feature that would make this null. Guard
            // it now while it costs nothing.
            'invited_by' => $this->when($this->invitedBy !== null, fn () => [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
                'avatar_url' => $this->invitedBy->profile_photo_url,
            ]),
            'invited_at' => $this->invited_at?->toIso8601String(),
        ];
    }
}
