<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TaskListMember;
use App\Models\User;
use App\Notifications\ListInvitationNotification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function forUser(User $user, bool $unreadOnly = false): Collection
    {
        $query = $unreadOnly ? $user->unreadNotifications() : $user->notifications();

        return $query->latest()->get();
    }

    public function unreadCountFor(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function findForUser(User $user, string $id): ?DatabaseNotification
    {
        return $user->notifications()->find($id);
    }

    public function setReadState(DatabaseNotification $notification, bool $read): DatabaseNotification
    {
        if ($read) {
            $notification->markAsRead();
        } else {
            $notification->markAsUnread();
        }

        return $notification->refresh();
    }

    public function updateInvitationState(
        TaskListMember $membership,
        User $user,
        string $status,
        bool $markRead,
    ): void {
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()
            ->where('type', ListInvitationNotification::class)
            ->latest()
            ->get()
            ->first(fn (DatabaseNotification $item): bool => ($item->data['membership_id'] ?? null) === $membership->id
                && ($item->data['status'] ?? null) === 'pending'
                && ($item->data['invited_at'] ?? null) === $membership->invited_at?->toIso8601String());

        if ($notification === null) {
            return;
        }

        $data = $notification->data;
        $data['status'] = $status;
        $attributes = ['data' => $data];

        if ($markRead) {
            $attributes['read_at'] = now();
        }

        $notification->forceFill($attributes)->save();
    }
}
