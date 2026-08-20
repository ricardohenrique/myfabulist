<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TaskListMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TaskListMember $membership,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $membership = $this->membership->loadMissing(['taskList', 'invitedBy']);

        return [
            'kind' => 'list_invitation',
            'membership_id' => $membership->id,
            'status' => 'pending',
            'invited_at' => $membership->invited_at?->toIso8601String(),
            'list' => [
                'id' => $membership->taskList->id,
                'name' => $membership->taskList->name,
            ],
            'actor' => $membership->invitedBy === null ? null : [
                'id' => $membership->invitedBy->id,
                'name' => $membership->invitedBy->name,
                'avatar_url' => $membership->invitedBy->profile_photo_url,
            ],
        ];
    }
}
