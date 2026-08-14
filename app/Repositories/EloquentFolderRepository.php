<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\FolderReorderMismatchException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
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

    /**
     * "Lists placed in this folder" means "lists with an accepted
     * membership row for this folder's owner whose folder_id is this
     * folder" (Plan 1, Step 2). Since Step 5 made `ListSharingService::accept()`
     * real, that placement can now include a list this folder's owner is
     * merely an accepted *member* of, not the owner of — filed into their
     * own folder via `updatePlacement()`. F11 (Plan 1, Step 6): this method
     * must never destroy a list the folder's owner does not own — every
     * placed list that isn't owned by `$folder->user_id` is left completely
     * untouched, with only the acting owner's own membership placement for
     * it detached to ungrouped, exactly like `detachLists()` does for every
     * list regardless of ownership.
     *
     * F12: even an *owned* list is not automatically eligible for
     * `forceDelete()` here. An owned list with more than one accepted member
     * is shared, and `TaskListService::delete()`'s single-list path already
     * guarantees a shared list is soft-deleted (recoverable), never
     * hard-deleted, when its owner removes it. This bulk folder path must
     * give the exact same guarantee — otherwise it is a second, easier way
     * for an owner to *irrecoverably* wipe every collaborator's copy of a
     * shared list, just by deleting the folder it happens to sit in. Only
     * an owned list nobody else has accepted membership on is force-deleted;
     * an owned-but-shared list is soft-deleted instead.
     */
    public function deleteWithLists(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $placedListIds = TaskListMember::query()
                ->where('user_id', $folder->user_id)
                ->where('folder_id', $folder->id)
                ->where('status', 'accepted')
                ->pluck('task_list_id');

            $ownedListIds = TaskList::query()
                ->whereIn('id', $placedListIds)
                ->where('user_id', $folder->user_id)
                ->pluck('id');

            $notOwnedListIds = $placedListIds->diff($ownedListIds);

            $sharedOwnedListIds = TaskListMember::query()
                ->whereIn('task_list_id', $ownedListIds)
                ->where('status', 'accepted')
                ->groupBy('task_list_id')
                ->havingRaw('count(*) > 1')
                ->pluck('task_list_id');

            $unsharedOwnedListIds = $ownedListIds->diff($sharedOwnedListIds);

            // forceDelete() only for owned lists nobody else has accepted
            // membership on: a real DELETE that lets tasks.task_list_id's FK
            // cascade destroy the tasks too (and, since Step 1, cascades the
            // now-orphaned task_list_members rows for these lists as well).
            TaskList::query()->whereIn('id', $unsharedOwnedListIds)->forceDelete();

            // An owned list that IS shared is soft-deleted instead (F12) —
            // the same recoverable, "everyone loses access but nothing is
            // destroyed" outcome TaskListService::delete() gives a shared
            // list on the single-list path. SoftDeletingScope::onDelete()
            // turns this bulk query-builder ->delete() into a mass
            // deleted_at update rather than a real DELETE, so the row (and
            // every member's task_list_members row against it, including
            // the owner's own) survives untouched — access, not existence,
            // is what changes here.
            TaskList::query()->whereIn('id', $sharedOwnedListIds)->delete();

            // Anything placed here that the actor does NOT own — a shared
            // list they are merely a member of — is never touched. Only
            // their own membership placement for it is ungrouped, exactly
            // like detachLists(). The list, its tasks, and every other
            // member's access are left completely intact.
            TaskListMember::query()
                ->where('user_id', $folder->user_id)
                ->whereIn('task_list_id', $notOwnedListIds)
                ->update(['folder_id' => null]);

            $folder->delete();
        });
    }

    public function detachLists(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            TaskListMember::query()
                ->where('user_id', $folder->user_id)
                ->where('folder_id', $folder->id)
                ->update(['folder_id' => null]);
            $folder->delete();
        });
    }

    public function hasLists(Folder $folder): bool
    {
        return TaskListMember::query()
            ->where('user_id', $folder->user_id)
            ->where('folder_id', $folder->id)
            ->where('status', 'accepted')
            ->whereHas('taskList')
            ->exists();
    }

    public function nextPosition(User $user): int
    {
        $maxPosition = Folder::query()->where('user_id', $user->id)->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    public function applyOrder(User $user, array $folderIds): void
    {
        DB::transaction(function () use ($user, $folderIds) {
            $currentIds = Folder::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $submittedIds = array_values($folderIds);
            $expectedIds = $currentIds;
            $candidateIds = $submittedIds;

            sort($expectedIds);
            sort($candidateIds);

            if ($expectedIds !== $candidateIds) {
                throw FolderReorderMismatchException::forCurrentFolders();
            }

            foreach ($submittedIds as $position => $folderId) {
                Folder::query()
                    ->where('user_id', $user->id)
                    ->where('id', $folderId)
                    ->update(['position' => $position]);
            }
        });
    }
}
