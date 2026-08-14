<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskListMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single membership row: an accepted member on the roster
 * (`TaskListMemberController::index()`), a freshly created invitation
 * (`TaskListMemberController::store()`), or the result of responding to one
 * (`ListInvitationController::{accept,decline}()`). Requires `taskList` to
 * be eager-loaded (`TaskListMemberRepositoryInterface::acceptedMembersFor()`
 * does this; `accept()`/`decline()` load it via `loadMissing()` inside
 * `ListSharingService`) — never pass a raw `User` model into a member
 * payload (R6): email is whitelisted to the list owner only (F18).
 *
 * @mixin TaskListMember
 */
class TaskListMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerOwnsTheList = $request->user()?->id === $this->taskList->user_id;

        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->profile_photo_url,
                'email' => $viewerOwnsTheList ? $this->user->email : null,
            ],
            'status' => $this->status,
            'is_owner' => $this->user_id === $this->taskList->user_id,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
        ];
    }
}
