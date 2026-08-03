<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTaskListRepository implements TaskListRepositoryInterface
{
    /**
     * @return Collection<int, TaskList>
     */
    public function allForUser(User $user): Collection
    {
        return TaskList::query()
            ->where('user_id', $user->id)
            ->with('folder')
            ->withCount('tasks')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function findForUser(int $taskListId, User $user): ?TaskList
    {
        return TaskList::query()
            ->where('user_id', $user->id)
            ->find($taskListId);
    }

    public function findDefaultFor(User $user): ?TaskList
    {
        return TaskList::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
    }

    public function createDefaultFor(User $user): TaskList
    {
        $taskList = new TaskList;

        $taskList->forceFill([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'Inbox',
            'is_default' => true,
            'position' => 0,
        ])->save();

        return $taskList;
    }

    public function create(User $user, string $name, ?Folder $folder, int $position): TaskList
    {
        $taskList = new TaskList;

        $taskList->forceFill([
            'user_id' => $user->id,
            'folder_id' => $folder?->id,
            'name' => $name,
            'is_default' => false,
            'position' => $position,
        ])->save();

        return $taskList;
    }

    public function update(TaskList $taskList, string $name, ?Folder $folder, int $position): TaskList
    {
        $taskList->forceFill([
            'name' => $name,
            'folder_id' => $folder?->id,
            'position' => $position,
        ])->save();

        return $taskList;
    }

    public function delete(TaskList $taskList): void
    {
        $taskList->delete();
    }

    public function nextPosition(User $user, ?int $folderId): int
    {
        $maxPosition = TaskList::query()
            ->where('user_id', $user->id)
            ->where('folder_id', $folderId)
            ->max('position');

        return $maxPosition === null ? 0 : (int) $maxPosition + 1;
    }

    public function applyOrder(User $user, ?int $folderId, array $taskListIds): void
    {
        DB::transaction(function () use ($user, $folderId, $taskListIds) {
            foreach (array_values($taskListIds) as $position => $taskListId) {
                TaskList::query()
                    ->where('user_id', $user->id)
                    ->where('folder_id', $folderId)
                    ->where('id', $taskListId)
                    ->update(['position' => $position]);
            }
        });
    }
}
