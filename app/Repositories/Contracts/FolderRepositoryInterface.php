<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface FolderRepositoryInterface
{
    /**
     * All of the user's folders, with their lists eager-loaded, ordered by
     * position.
     *
     * @return Collection<int, Folder>
     */
    public function allForUser(User $user): Collection;

    /**
     * Find a folder by id, scoped to the given user. Returns null when the
     * folder does not exist or belongs to a different user.
     */
    public function findForUser(int $folderId, User $user): ?Folder;

    public function create(User $user, string $name, int $position): Folder;

    public function rename(Folder $folder, string $name): Folder;

    /**
     * Delete the folder. Callers must ensure it has no lists first (D6);
     * this method performs no such check itself.
     */
    public function delete(Folder $folder): void;

    /**
     * Delete the folder and destroy the lists placed inside it, atomically —
     * scoped strictly to the folder owner's *own* placements (Plan 1, Step
     * 6/F11): a list placed here that the folder's owner does not own (an
     * accepted membership on someone else's shared list) is never touched —
     * only the owner's own membership placement for it is detached to
     * ungrouped, exactly like `detachLists()`.
     *
     * F12: even an *owned* list is not unconditionally destroyed. An owned,
     * unshared list is hard-deleted (and, transitively, its tasks). An
     * owned list that is shared (more than one accepted member) is
     * soft-deleted instead — the same recoverable outcome
     * `TaskListService::delete()` guarantees a shared list on the
     * single-list deletion path, reached here via the folder-bulk path.
     */
    public function deleteWithLists(Folder $folder): void;

    /**
     * Move every list out of the folder (folder_id = null), then delete it,
     * atomically. This only ever nulls the folder owner's *own*
     * `task_list_members.folder_id` — it never inspects or depends on who
     * owns each list, so it needs no ownership scoping: a list this folder's
     * owner merely has accepted membership on (someone else's shared list)
     * is ungrouped for them exactly like one they own, and neither the list
     * nor any other member's row is ever touched.
     */
    public function detachLists(Folder $folder): void;

    public function hasLists(Folder $folder): bool;

    public function nextPosition(User $user): int;

    /**
     * Rewrite the position of every folder in $folderIds to match the
     * array's order, atomically.
     *
     * @param  array<int, int>  $folderIds
     */
    public function applyOrder(User $user, array $folderIds): void;
}
