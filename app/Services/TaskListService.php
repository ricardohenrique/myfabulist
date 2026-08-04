<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskListService
{
    public function __construct(
        private readonly TaskListRepositoryInterface $taskLists,
        private readonly FolderRepositoryInterface $folders,
    ) {}

    /**
     * The user's Inbox — always exists, never lives in a folder. Idempotent
     * and self-healing: creates the row on first access if the Registered
     * listener has not run for this user (D5). This is the single read path
     * every caller (web and API) must use to resolve the Inbox.
     */
    public function inboxFor(User $user): TaskList
    {
        return $this->taskLists->findDefaultFor($user)
            ?? $this->taskLists->createDefaultFor($user);
    }

    /**
     * @return Collection<int, TaskList>
     */
    public function allFor(User $user): Collection
    {
        return $this->taskLists->allForUser($user);
    }

    /**
     * Create a list, in a folder or ungrouped (M2). The folder reference
     * arrives as an id and is resolved here, scoped to the owner (D3) —
     * a missing or foreign folder id throws regardless of which delivery
     * mechanism called in.
     */
    public function create(User $user, string $name, ?int $folderId): TaskList
    {
        $folder = $this->resolveFolder($user, $folderId);
        $position = $this->taskLists->nextPosition($user, $folder?->id);

        return $this->taskLists->create($user, trim($name), $folder, $position);
    }

    /**
     * Rename and/or move a list between folders in one call (M2). The
     * Inbox may be renamed but is forced back to ungrouped regardless of
     * what is requested (D5) — it can never live in a folder.
     */
    public function update(TaskList $taskList, User $user, string $name, ?int $folderId): TaskList
    {
        $folder = $taskList->is_default ? null : $this->resolveFolder($user, $folderId);

        $position = $folder?->id === $taskList->folder_id
            ? $taskList->position
            : $this->taskLists->nextPosition($user, $folder?->id);

        return $this->taskLists->update($taskList, trim($name), $folder, $position);
    }

    /**
     * Delete a list (M2). The default (Inbox) list can never be deleted (D5).
     */
    public function delete(TaskList $taskList): void
    {
        if ($taskList->is_default) {
            throw DefaultTaskListCannotBeDeletedException::for($taskList);
        }

        $this->taskLists->delete($taskList);
    }

    /**
     * Un-deletes a soft-deleted list, bringing back every task it contained
     * exactly as it was (D2 — the tasks were never touched by the delete).
     * No `is_default` guard is needed here: the Inbox can never be deleted
     * in the first place (see `delete()` above), so it can never reach this
     * method in a trashed state.
     */
    public function undelete(TaskList $taskList): TaskList
    {
        return $this->taskLists->undelete($taskList);
    }

    /**
     * @param  array<int, int>  $taskListIds
     */
    public function reorder(User $user, ?int $folderId, array $taskListIds): void
    {
        $this->taskLists->applyOrder($user, $folderId, $taskListIds);
    }

    /**
     * Resolve a folder reference scoped to its owner. Throws when the
     * folder does not exist or belongs to a different user (D3) — cross-user
     * reference injection is impossible regardless of caller.
     */
    private function resolveFolder(User $user, ?int $folderId): ?Folder
    {
        if ($folderId === null) {
            return null;
        }

        return $this->folders->findForUser($folderId, $user)
            ?? throw FolderNotFoundException::forId($folderId);
    }
}
