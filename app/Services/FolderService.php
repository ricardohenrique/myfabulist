<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FolderNotEmptyException;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\Contracts\FolderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
    ) {}

    /**
     * @return Collection<int, Folder>
     */
    public function allFor(User $user): Collection
    {
        return $this->folders->allForUser($user);
    }

    public function create(User $user, string $name): Folder
    {
        return $this->folders->create($user, trim($name), $this->folders->nextPosition($user));
    }

    public function rename(Folder $folder, string $name): Folder
    {
        return $this->folders->rename($folder, trim($name));
    }

    /**
     * Delete an empty folder. Throws when the folder still holds lists (M1) —
     * callers must choose deleteWithLists() or detachLists() instead.
     */
    public function delete(Folder $folder): void
    {
        if ($this->folders->hasLists($folder)) {
            throw FolderNotEmptyException::for($folder);
        }

        $this->folders->delete($folder);
    }

    public function deleteWithLists(Folder $folder): void
    {
        $this->folders->deleteWithLists($folder);
    }

    public function detachLists(Folder $folder): void
    {
        $this->folders->detachLists($folder);
    }

    /**
     * @param  array<int, int>  $folderIds
     */
    public function reorder(User $user, array $folderIds): void
    {
        $this->folders->applyOrder($user, $folderIds);
    }
}
