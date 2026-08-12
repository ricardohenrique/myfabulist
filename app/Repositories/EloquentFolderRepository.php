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
     * "Lists placed in this folder" now means "lists with an accepted
     * membership row for this folder's owner whose folder_id is this
     * folder" (Plan 1, Step 2) rather than a live task_lists.folder_id
     * relation — folders are never shareable and there is no functional
     * invite/accept path yet, so this can today only ever resolve to lists
     * this same owner also owns, making the rewrite behaviourally identical
     * to the old relation-based query. F11's "only force-delete lists the
     * actor owns" guard is deliberately not added here — that is Step 6's
     * job, once a member could plausibly file another owner's shared list
     * into their own folder.
     */
    public function deleteWithLists(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $listIds = TaskListMember::query()
                ->where('user_id', $folder->user_id)
                ->where('folder_id', $folder->id)
                ->where('status', 'accepted')
                ->pluck('task_list_id');

            // forceDelete() preserves today's exact semantics: a real
            // DELETE that lets tasks.task_list_id's FK cascade destroy the
            // tasks too (and, since Step 1, cascades the now-orphaned
            // task_list_members rows for these lists as well).
            TaskList::query()->whereIn('id', $listIds)->forceDelete();
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
