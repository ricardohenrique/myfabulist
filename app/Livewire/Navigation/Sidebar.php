<?php

declare(strict_types=1);

namespace App\Livewire\Navigation;

use App\Concerns\ReordersIds;
use App\Exceptions\DomainException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\Data\NavigationFolder;
use App\Services\Data\NavigationTree;
use App\Services\FolderService;
use App\Services\NavigationService;
use App\Services\TaskListService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The sidebar tree: Inbox, Starred, folders with their lists, and ungrouped
 * lists (A1). Owns navigation/highlight state only — create/rename/delete
 * still live in FolderDialog/ListDialog (R6). Reordering (S4/Step 3) is the
 * one exception: it is a single-array position rewrite with no additional
 * business rule, so it is owned here rather than adding a third dialog.
 *
 * @property-read NavigationTree $tree
 */
class Sidebar extends Component
{
    use ReordersIds;

    public ?string $currentRouteName = null;

    public ?int $currentTaskListId = null;

    /**
     * Captures the "current item" once from the route (A2). A later AJAX
     * re-render happens against the `livewire.update` route, so reading
     * request() at render time would silently point at the wrong route —
     * this is why the values are read here, once, and never again.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', TaskList::class);

        $this->currentRouteName = request()->route()?->getName();

        $routeList = request()->route('list');

        $this->currentTaskListId = match (true) {
            $routeList instanceof TaskList => $routeList->id,
            is_numeric($routeList) => (int) $routeList,
            default => null,
        };
    }

    #[Computed]
    public function tree(): NavigationTree
    {
        return app(NavigationService::class)->treeFor(Auth::user());
    }

    #[On('navigation-changed')]
    public function refreshTree(): void
    {
        unset($this->tree);
    }

    /**
     * Move-up button (S4/Step 3). A no-op when the list is already first in
     * its scope (its folder, or the ungrouped group).
     */
    public function moveListUp(int $listId, TaskListRepositoryInterface $taskLists, TaskListService $taskListService): void
    {
        $list = $this->authorizedList($listId, $taskLists);

        $ids = $this->listIdsIn($list->folder_id);
        $index = array_search($listId, $ids, true);

        if ($index === false || $index === 0) {
            return;
        }

        $this->applyListReorder($taskListService, $list->folder_id, $ids, $listId, $index - 1);
    }

    /**
     * Move-down button (S4/Step 3). A no-op when the list is already last.
     */
    public function moveListDown(int $listId, TaskListRepositoryInterface $taskLists, TaskListService $taskListService): void
    {
        $list = $this->authorizedList($listId, $taskLists);

        $ids = $this->listIdsIn($list->folder_id);
        $index = array_search($listId, $ids, true);

        if ($index === false || $index === count($ids) - 1) {
            return;
        }

        $this->applyListReorder($taskListService, $list->folder_id, $ids, $listId, $index + 1);
    }

    /**
     * The list drag entry point (S4/Step 3). $folderId is the *container*
     * the drop happened in (root/ungrouped when null, via the parameter's
     * default rather than an explicit null argument — see
     * sidebar.blade.php's wire:sort:group-id usage) — distinct
     * `wire:sort:group` names per container already stop Sortable from
     * accepting a cross-folder drop client-side (C6), but a stale or
     * hand-crafted request could still name a list that does not actually
     * belong to $folderId. TaskListService::reorder() writes positions
     * scoped to $folderId regardless (R6/C6): a mismatch here is refused
     * before any write, exactly like a cross-folder drag would be.
     *
     * $listId/$position are nullable defensively, not just $folderId: a
     * drag that ends outside any valid drop target (or is cancelled) can
     * still reach here without a resolved key/position. Typing them as
     * plain int would let that surface as a TypeError instead of the same
     * graceful "stale — refreshing" toast a bad drop already gets.
     */
    public function reorderList(?int $listId, ?int $position, TaskListRepositoryInterface $taskLists, TaskListService $taskListService, ?int $folderId = null): void
    {
        if ($listId === null || $position === null) {
            $this->refuseStaleReorder();

            return;
        }

        $list = $this->authorizedList($listId, $taskLists);

        if ($list->folder_id !== $folderId) {
            $this->refuseStaleReorder();

            return;
        }

        $this->applyListReorder($taskListService, $folderId, $this->listIdsIn($folderId), $listId, $position);
    }

    /**
     * Move-up button for a folder row (S4/Step 3).
     */
    public function moveFolderUp(int $folderId, FolderRepositoryInterface $folders, FolderService $folderService): void
    {
        $this->authorizedFolder($folderId, $folders);

        $ids = $this->folderIds();
        $index = array_search($folderId, $ids, true);

        if ($index === false || $index === 0) {
            return;
        }

        $this->applyFolderReorder($folderService, $ids, $folderId, $index - 1);
    }

    /**
     * Move-down button for a folder row (S4/Step 3).
     */
    public function moveFolderDown(int $folderId, FolderRepositoryInterface $folders, FolderService $folderService): void
    {
        $this->authorizedFolder($folderId, $folders);

        $ids = $this->folderIds();
        $index = array_search($folderId, $ids, true);

        if ($index === false || $index === count($ids) - 1) {
            return;
        }

        $this->applyFolderReorder($folderService, $ids, $folderId, $index + 1);
    }

    /**
     * The folder drag entry point (S4/Step 3). Nullable for the same reason
     * as reorderList() above: a `wire:sort` drag can call this with an
     * unresolved (null) key/position on the client side, and that must
     * degrade to the same stale-reorder toast rather than a TypeError.
     */
    public function reorderFolder(?int $folderId, ?int $position, FolderRepositoryInterface $folders, FolderService $folderService): void
    {
        if ($folderId === null || $position === null) {
            $this->refuseStaleReorder();

            return;
        }

        $this->authorizedFolder($folderId, $folders);

        $this->applyFolderReorder($folderService, $this->folderIds(), $folderId, $position);
    }

    private function authorizedList(int $listId, TaskListRepositoryInterface $taskLists): TaskList
    {
        $list = $taskLists->findForUser($listId, Auth::user());

        abort_if($list === null, 404);

        Gate::authorize('update', $list);

        return $list;
    }

    private function authorizedFolder(int $folderId, FolderRepositoryInterface $folders): Folder
    {
        $folder = $folders->findForUser($folderId, Auth::user());

        abort_if($folder === null, 404);

        Gate::authorize('update', $folder);

        return $folder;
    }

    /**
     * The current order of lists in one scope (a folder, or the ungrouped
     * group when null), read from the same tree() the sidebar just
     * rendered — never a fresh query (C2).
     *
     * @return array<int, int>
     */
    private function listIdsIn(?int $folderId): array
    {
        if ($folderId === null) {
            return $this->tree->ungroupedLists
                ->map(fn (TaskList $list): int => $list->id)
                ->values()
                ->all();
        }

        $navigationFolder = collect($this->tree->folders)
            ->first(fn (NavigationFolder $navigationFolder): bool => $navigationFolder->folder->id === $folderId);

        if ($navigationFolder === null) {
            return [];
        }

        return $navigationFolder->lists
            ->map(fn (TaskList $list): int => $list->id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function folderIds(): array
    {
        return collect($this->tree->folders)
            ->map(fn (NavigationFolder $navigationFolder): int => $navigationFolder->folder->id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function applyListReorder(TaskListService $taskListService, ?int $folderId, array $ids, int $listId, int $position): void
    {
        try {
            $taskListService->reorder(Auth::user(), $folderId, $this->reorderedIds($ids, $listId, $position));
        } catch (DomainException) {
            $this->refuseStaleReorder();

            return;
        }

        unset($this->tree);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function applyFolderReorder(FolderService $folderService, array $ids, int $folderId, int $position): void
    {
        try {
            $folderService->reorder(Auth::user(), $this->reorderedIds($ids, $folderId, $position));
        } catch (DomainException) {
            $this->refuseStaleReorder();

            return;
        }

        unset($this->tree);
    }

    /**
     * The list/folder repositories scope applyOrder() writes by user (and,
     * for lists, by folder) rather than validating the submitted id set the
     * way TaskRepository::applyOrder() does — reorderList()'s folder-match
     * guard above is what actually refuses a foreign id (R6/C6). This is
     * still called on the (currently unreachable, but not impossible after
     * a future repository change) DomainException path, and always busts
     * the cache and shows the same friendly message the drag failure path
     * uses (C3/R1) so a stale tree never lingers on screen.
     */
    private function refuseStaleReorder(): void
    {
        unset($this->tree);
        Flux::toast(variant: 'danger', text: __('That list changed — refreshing.'));
    }

    public function render(): View
    {
        return view('livewire.navigation.sidebar');
    }
}
