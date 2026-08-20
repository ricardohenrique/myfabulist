<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly TaskListMemberRepositoryInterface $members,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function unreadCountFor(User $user): int
    {
        return $this->notifications->unreadCountFor($user);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user, bool $unreadOnly = false): Collection
    {
        return $this->present($user, $this->notifications->forUser($user, $unreadOnly));
    }

    public function findForUser(User $user, string $id): DatabaseNotification
    {
        return $this->notifications->findForUser($user, $id)
            ?? throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function itemFor(User $user, DatabaseNotification $notification): array
    {
        return $this->present($user, new EloquentCollection([$notification]))->firstOrFail();
    }

    public function setReadState(DatabaseNotification $notification, bool $read): DatabaseNotification
    {
        return $this->notifications->setReadState($notification, $read);
    }

    public function recordInvitationResponse(TaskListMember $membership, User $user, string $status): void
    {
        $this->notifications->updateInvitationState($membership, $user, $status, true);
    }

    public function recordInvitationRevoked(TaskListMember $membership): void
    {
        $this->notifications->updateInvitationState(
            $membership,
            $membership->loadMissing('user')->user,
            'revoked',
            false,
        );
    }

    /**
     * @param  EloquentCollection<int, DatabaseNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function present(User $user, EloquentCollection $notifications): Collection
    {
        $listIds = $notifications
            ->map(fn (DatabaseNotification $notification): ?int => $this->integerAt($notification->data, 'list.id'))
            ->filter()
            ->unique()
            ->values();
        $taskIds = $notifications
            ->map(fn (DatabaseNotification $notification): ?int => $this->integerAt($notification->data, 'task.id'))
            ->filter()
            ->unique()
            ->values();
        $membershipIds = $notifications
            ->map(fn (DatabaseNotification $notification): ?int => $this->integerAt($notification->data, 'membership_id'))
            ->filter()
            ->unique()
            ->values();

        $accessibleListIds = $this->members
            ->acceptedListIdsFor($user, $listIds->all())
            ->mapWithKeys(fn (int $id): array => [$id => true]);

        $availableTaskIds = $this->tasks
            ->existingIdsInLists($taskIds->all(), $accessibleListIds->keys()->all())
            ->mapWithKeys(fn (int $id): array => [$id => true]);

        $memberships = $this->members
            ->forUserByIds($user, $membershipIds->all())
            ->keyBy('id');

        return $notifications
            ->map(fn (DatabaseNotification $notification): array => $this->presentNotification(
                $notification,
                $accessibleListIds,
                $availableTaskIds,
                $memberships,
            ))
            ->values();
    }

    /**
     * @param  Collection<int, bool>  $accessibleListIds
     * @param  Collection<int, bool>  $availableTaskIds
     * @param  EloquentCollection<int, TaskListMember>  $memberships
     * @return array<string, mixed>
     */
    private function presentNotification(
        DatabaseNotification $notification,
        Collection $accessibleListIds,
        Collection $availableTaskIds,
        EloquentCollection $memberships,
    ): array {
        $data = $notification->data;
        $kind = (string) ($data['kind'] ?? 'unknown');
        $listId = $this->integerAt($data, 'list.id');
        $taskId = $this->integerAt($data, 'task.id');
        $membershipId = $this->integerAt($data, 'membership_id');
        $status = (string) ($data['status'] ?? 'unavailable');

        if ($kind === 'list_invitation' && $status === 'pending') {
            $membership = $membershipId === null ? null : $memberships->get($membershipId);
            $sameAttempt = $membership !== null
                && $membership->status === 'pending'
                && $membership->invited_at?->toIso8601String() === ($data['invited_at'] ?? null);

            if (! $sameAttempt) {
                $status = 'unavailable';
            }
        }

        $targetUrl = match (true) {
            $kind === 'task_comment'
                && $listId !== null
                && $taskId !== null
                && $accessibleListIds->has($listId)
                && $availableTaskIds->has($taskId) => route('lists.show', ['list' => $listId, 'task' => $taskId], false),
            $kind === 'list_invitation'
                && $status === 'accepted'
                && $listId !== null
                && $accessibleListIds->has($listId) => route('lists.show', $listId, false),
            default => null,
        };

        return [
            'id' => $notification->id,
            'type' => $kind,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String() ?? '',
            'actor' => [
                'id' => $this->integerAt($data, 'actor.id'),
                'name' => (string) data_get($data, 'actor.name', 'Someone'),
                'avatarUrl' => $this->nullableStringAt($data, 'actor.avatar_url'),
            ],
            'list' => [
                'id' => $listId,
                'name' => (string) data_get($data, 'list.name', 'a shared list'),
            ],
            'task' => $kind === 'task_comment' ? [
                'id' => $taskId,
                'title' => (string) data_get($data, 'task.title', 'a task'),
            ] : null,
            'body' => $kind === 'task_comment' ? (string) ($data['body'] ?? '') : null,
            'invitation' => $kind === 'list_invitation' ? [
                'id' => $membershipId,
                'status' => $status,
                'canRespond' => $status === 'pending',
            ] : null,
            'targetUrl' => $targetUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function integerAt(array $data, string $key): ?int
    {
        $value = data_get($data, $key);

        return is_int($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nullableStringAt(array $data, string $key): ?string
    {
        $value = data_get($data, $key);

        return is_string($value) ? $value : null;
    }
}
