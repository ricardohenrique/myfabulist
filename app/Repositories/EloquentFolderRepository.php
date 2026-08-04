<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Folder;
use App\Models\User;
use App\Repositories\Contracts\FolderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentFolderRepository implements FolderRepositoryInterface
{
    /**
     * @return Collection<int, Folder>
     */
    public function allForUser(User $user): Collection
    {
        return Folder::query()
            ->where('user_id', $user->id)
            ->with('taskLists')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function findForUser(int $folderId, User $user): ?Folder
    {
        return Folder::query()
            ->where('user_id', $user->id)
            ->find($folderId);
    }

    public function create(User $user, string $name, int $position): Folder
    {
        $folder = new Folder;

        $folder->forceFill([
            'user_id' => $user->id,
            'name' => $name,
            'position' => $position,
        ])->save();

        return $folder;
    }

    public function rename(Folder $folder, string $name): Folder
    {
        $folder->forceFill(['name' => $name])->save();

        return $folder;
    }

    public function delete(Folder $folder): void
    {
        $folder->delete();
    }

    public function deleteWithLists(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            // Folder deletion is deliberately irreversible (D5/Plan 4) — a
            // plain ->delete() would now be a mass *soft* delete once
            // TaskList uses SoftDeletes, silently skipping the FK cascade
            // and leaving the lists' tasks alive but permanently
            // unreachable (no Trash UI exists). forceDelete() preserves
            // today's exact semantics: a real DELETE that lets the FK
            // cascade destroy the tasks too.
            $folder->taskLists()->forceDelete();
            $folder->delete();
        });
    }

    public function detachLists(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $folder->taskLists()->update(['folder_id' => null]);
            $folder->delete();
        });
    }

    public function hasLists(Folder $folder): bool
    {
        return $folder->taskLists()->exists();
    }

    public function nextPosition(User $user): int
    {
        $maxPosition = Folder::query()->where('user_id', $user->id)->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    public function applyOrder(User $user, array $folderIds): void
    {
        DB::transaction(function () use ($user, $folderIds) {
            foreach (array_values($folderIds) as $position => $folderId) {
                Folder::query()
                    ->where('user_id', $user->id)
                    ->where('id', $folderId)
                    ->update(['position' => $position]);
            }
        });
    }
}
