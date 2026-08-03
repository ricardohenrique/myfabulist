<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TaskListRepositoryInterface
{
    /**
     * All of the user's lists, with folder eager-loaded and tasks counted.
     *
     * @return Collection<int, TaskList>
     */
    public function allForUser(User $user): Collection;

    /**
     * Find a list by id, scoped to the given user. Returns null when the
     * list does not exist or belongs to a different user.
     */
    public function findForUser(int $taskListId, User $user): ?TaskList;

    public function findDefaultFor(User $user): ?TaskList;

    /**
     * Create the user's default Inbox list. The sole creation point for the
     * default list (D5).
     */
    public function createDefaultFor(User $user): TaskList;

    public function create(User $user, string $name, ?Folder $folder, int $position): TaskList;

    public function update(TaskList $taskList, string $name, ?Folder $folder, int $position): TaskList;

    public function delete(TaskList $taskList): void;

    public function nextPosition(User $user, ?int $folderId): int;

    /**
     * Rewrite the position of every list in $taskListIds to match the
     * array's order, atomically.
     *
     * @param  array<int, int>  $taskListIds
     */
    public function applyOrder(User $user, ?int $folderId, array $taskListIds): void;
}
