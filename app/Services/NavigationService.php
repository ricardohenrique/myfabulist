<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\Data\NavigationFolder;
use App\Services\Data\NavigationTree;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The sidebar's read model (A4). Composes the Inbox, folders and lists into
 * a single NavigationTree without touching Eloquent directly — every row
 * comes from a repository call, and folders are matched to their lists on
 * the `folder_id` attribute rather than the `TaskList::folder` relation, so
 * this can never trip Model::preventLazyLoading().
 */
class NavigationService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folders,
        private readonly TaskListRepositoryInterface $taskLists,
        private readonly TaskListService $taskListService,
    ) {}

    public function treeFor(User $user): NavigationTree
    {
        $inbox = $this->taskListService->inboxFor($user);

        $folders = $this->folders->allForUser($user);

        $lists = $this->taskLists->allForUser($user);

        // allForUser() is where task counts live (A5); use the counted row
        // for the Inbox badge instead of the uncounted one inboxFor() may
        // have just self-healed into existence, so the count is never stale.
        $countedInbox = $lists->firstWhere('id', $inbox->id);

        if ($countedInbox !== null) {
            $inbox = $countedInbox;
        }

        $listsByFolder = $lists
            ->reject(fn (TaskList $taskList): bool => $taskList->is_default)
            ->groupBy('folder_id');

        $navigationFolders = $folders
            ->map(fn (Folder $folder): NavigationFolder => new NavigationFolder(
                folder: $folder,
                lists: $this->listsGroupedUnder($listsByFolder, $folder->id),
            ))
            ->values()
            ->all();

        return new NavigationTree(
            inbox: $inbox,
            folders: $navigationFolders,
            ungroupedLists: $this->listsGroupedUnder($listsByFolder, null),
        );
    }

    /**
     * Collection::groupBy() casts a null group key to an empty string, so
     * the ungrouped bucket lives under '' rather than null — this is the
     * one place that quirk needs to be known.
     *
     * @param  Collection<int|string, EloquentCollection<int, TaskList>>  $listsByFolder
     * @return EloquentCollection<int, TaskList>
     */
    private function listsGroupedUnder(Collection $listsByFolder, ?int $folderId): EloquentCollection
    {
        return $listsByFolder->get($folderId ?? '') ?? new EloquentCollection;
    }
}
