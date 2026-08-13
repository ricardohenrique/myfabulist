<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DefaultTaskListCannotBeDeletedException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\NotListOwnerException;
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
     * Attach `accepted_members_count` onto a single, already-resolved list
     * (Plan 1, Step 7) — the single-list counterpart to `allFor()`, used by
     * `TaskListController::show()` so `TaskListResource`'s `is_shared`/
     * `member_count` are available there too, not just on the index.
     */
    public function withMemberCount(TaskList $taskList): TaskList
    {
        return $this->taskLists->withAcceptedMemberCount($taskList);
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

        return $this->taskLists->update($taskList, $user, trim($name), $folder, $position);
    }

    /**
     * Delete a list (M2). The default (Inbox) list can never be deleted (D5).
     *
     * `$user` is required and re-checked against ownership here as defence
     * in depth (F10/F12, Plan 1, Step 6) — both web and API controllers
     * already call `$this->authorize('delete', $list)` (owner-only,
     * `TaskListPolicy::delete()`) immediately before reaching this method,
     * but the Service must never trust that alone, matching
     * `EloquentTaskListRepository::update()`'s own membership re-check and
     * `ListSharingService::revoke()`'s own ownership re-check. A non-owner —
     * even an accepted member — can never delete a shared list outright,
     * only leave it (`ListSharingService::leave()`). The `is_default` check
     * runs first, mirroring `TaskListPolicy::delete()`'s own check order: the
     * Inbox can never be deleted regardless of who is asking, so that
     * structural fact is the more fundamental failure to surface first.
     */
    public function delete(TaskList $taskList, User $user): void
    {
        if ($taskList->is_default) {
            throw DefaultTaskListCannotBeDeletedException::for($taskList);
        }

        if ($user->id !== $taskList->user_id) {
            throw NotListOwnerException::forDelete($taskList, $user);
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
