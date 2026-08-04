<?php

declare(strict_types=1);

namespace App\Livewire\Navigation;

use App\Exceptions\DomainException;
use App\Models\Folder;
use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\FolderService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Folder create/rename/delete (M1), including the three-way non-empty-delete
 * choice (A7). Every mutation below is an existing FolderService call — no
 * new business logic is written here.
 */
class FolderDialog extends Component
{
    public ?int $currentTaskListId = null;

    public ?int $folderId = null;

    public string $name = '';

    public string $mode = 'create';

    public bool $folderHasLists = false;

    #[On('folder-dialog-open')]
    public function open(string $mode, ?int $folderId, FolderRepositoryInterface $folders): void
    {
        $this->resetErrorBag();
        $this->mode = $mode;
        $this->folderId = $folderId;
        $this->name = '';
        $this->folderHasLists = false;

        if ($folderId === null) {
            Gate::authorize('create', Folder::class);
        } else {
            $folder = $this->resolveFolder($folderId, $folders);
            $this->name = $folder->name;

            Gate::authorize($mode === 'delete' ? 'delete' : 'update', $folder);

            if ($mode === 'delete') {
                $this->folderHasLists = $folders->hasLists($folder);
            }
        }

        Flux::modal('folder-dialog')->show();
    }

    /**
     * Handles both create (no folderId yet) and rename (Architecture Review
     * — the two share the same "one name field" shape).
     */
    public function save(FolderService $folderService, FolderRepositoryInterface $folders): void
    {
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            if ($this->folderId === null) {
                Gate::authorize('create', Folder::class);
                $folderService->create(Auth::user(), $this->name);
            } else {
                $folder = $this->resolveFolder($this->folderId, $folders);
                Gate::authorize('update', $folder);
                $folderService->rename($folder, $this->name);
            }
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->announceAndClose(__('Folder saved.'));
    }

    /**
     * The single-button delete path for a folder that holds no lists.
     * FolderService::delete() still throws FolderNotEmptyException as
     * defence in depth if a list was added between opening this dialog and
     * submitting it.
     */
    public function deleteEmptyFolder(FolderService $folderService, FolderRepositoryInterface $folders): void
    {
        $folder = $this->resolveFolder($this->folderId, $folders);
        Gate::authorize('delete', $folder);

        try {
            $folderService->delete($folder);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->announceAndClose(__('Folder deleted.'));
    }

    /**
     * FolderService::detachLists() also deletes the folder (R2) — it must
     * never be followed by a call to delete().
     */
    public function moveListsOut(FolderService $folderService, FolderRepositoryInterface $folders): void
    {
        $folder = $this->resolveFolder($this->folderId, $folders);
        Gate::authorize('delete', $folder);

        $folderService->detachLists($folder);

        $this->announceAndClose(__('Lists moved out and folder deleted.'));
    }

    /**
     * Cascades folder + lists + tasks. If the currently open list lived in
     * this folder, it no longer exists — redirect to the Inbox (A8) rather
     * than leave the user on a dead page.
     */
    public function deleteFolderAndLists(
        FolderService $folderService,
        FolderRepositoryInterface $folders,
        TaskListRepositoryInterface $taskLists,
    ): void {
        $folder = $this->resolveFolder($this->folderId, $folders);
        Gate::authorize('delete', $folder);

        $currentListWasInFolder = $this->currentListBelongsTo($folder, $taskLists);

        $folderService->deleteWithLists($folder);

        $this->announceAndClose(__('Folder and its lists deleted.'));

        if ($currentListWasInFolder) {
            $this->redirectRoute('inbox', navigate: true);
        }
    }

    private function currentListBelongsTo(Folder $folder, TaskListRepositoryInterface $taskLists): bool
    {
        if ($this->currentTaskListId === null) {
            return false;
        }

        $currentList = $taskLists->findForUser($this->currentTaskListId, Auth::user());

        return $currentList?->folder_id === $folder->id;
    }

    private function resolveFolder(?int $folderId, FolderRepositoryInterface $folders): Folder
    {
        $folder = $folderId === null ? null : $folders->findForUser($folderId, Auth::user());

        abort_if($folder === null, 404);

        return $folder;
    }

    private function announceAndClose(string $message): void
    {
        Flux::modal('folder-dialog')->close();
        $this->dispatch('navigation-changed');
        Flux::toast(variant: 'success', text: $message);
    }

    public function render(): View
    {
        return view('livewire.navigation.folder-dialog');
    }
}
