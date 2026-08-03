<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface FolderRepositoryInterface
{
    /**
     * All of the user's folders, with their lists eager-loaded, ordered by position.
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
     * Delete the folder and every list (and, transitively, task) inside it,
     * atomically.
     */
    public function deleteWithLists(Folder $folder): void;

    /**
     * Move every list out of the folder (folder_id = null), then delete it,
     * atomically.
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
