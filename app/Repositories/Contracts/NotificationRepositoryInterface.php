<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationRepositoryInterface
{
    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function forUser(User $user, bool $unreadOnly = false): Collection;

    public function unreadCountFor(User $user): int;

    public function findForUser(User $user, string $id): ?DatabaseNotification;

    public function setReadState(DatabaseNotification $notification, bool $read): DatabaseNotification;

    public function updateInvitationState(
        TaskListMember $membership,
        User $user,
        string $status,
        bool $markRead,
    ): void;
}
