<?php

declare(strict_types=1);

namespace App\Livewire\Navigation;

use App\Exceptions\DomainException;
use App\Models\Folder;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\FolderService;
use App\Services\TaskListService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * List create/rename/move/delete (M2). Rename and move share one call —
 * TaskListService::update() has replace semantics (R5), so save() always
 * sends both the name and the folder id, never a blank name just to move.
 */
class ListDialog extends Component
{
    public ?int $currentTaskListId = null;

    public ?int $listId = null;

    public string $name = '';

    public ?int $folderId = null;

    public string $mode = 'create';

    /**
     * @param  int|null  $folderId  Pre-selects the folder when the user
     *                              clicked "+" on a folder row.
     */
    #[On('list-dialog-open')]
    public function open(string $mode, ?int $listId, ?int $folderId, TaskListRepositoryInterface $taskLists): void
    {
        $this->resetErrorBag();
        $this->mode = $mode;
        $this->listId = $listId;
        $this->folderId = $folderId;
        $this->name = '';

        if ($listId === null) {
            Gate::authorize('create', TaskList::class);
        } else {
            $list = $this->resolveList($listId, $taskLists);
            $this->name = $list->name;
            $this->folderId = $list->folder_id;

            Gate::authorize($mode === 'delete' ? 'delete' : 'update', $list);
        }

        Flux::modal('list-dialog')->show();
    }

    /**
     * @return Collection<int, Folder>
     */
    #[Computed]
    public function folders(): Collection
    {
        return app(FolderService::class)->allFor(Auth::user());
    }

    public function save(TaskListService $taskListService, TaskListRepositoryInterface $taskLists): void
    {
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'folderId' => ['nullable', 'integer'],
        ]);

        try {
            if ($this->listId === null) {
                Gate::authorize('create', TaskList::class);
                $taskListService->create(Auth::user(), $this->name, $this->folderId);
            } else {
                $list = $this->resolveList($this->listId, $taskLists);
                Gate::authorize('update', $list);
                // Always both name and folder id — TaskListService::update()
                // has replace semantics (R5): a blank name here would
                // silently rename the list while moving it.
                $taskListService->update($list, Auth::user(), $this->name, $this->folderId);
            }
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->announceAndClose(__('List saved.'));
    }

    /**
     * The default (Inbox) list can never be deleted (D5) — that ability
     * check denies it here defensively even though A9 already keeps the
     * delete affordance out of the sidebar for the Inbox.
     */
    public function delete(TaskListService $taskListService, TaskListRepositoryInterface $taskLists): void
    {
        $list = $this->resolveList($this->listId, $taskLists);
        Gate::authorize('delete', $list);

        $wasCurrentList = $this->currentTaskListId === $list->id;

        try {
            $taskListService->delete($list);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->announceAndClose(__('List deleted.'));

        if ($wasCurrentList) {
            $this->redirectRoute('inbox', navigate: true);
        }
    }

    private function resolveList(?int $listId, TaskListRepositoryInterface $taskLists): TaskList
    {
        $list = $listId === null ? null : $taskLists->findForUser($listId, Auth::user());

        abort_if($list === null, 404);

        return $list;
    }

    private function announceAndClose(string $message): void
    {
        Flux::modal('list-dialog')->close();
        $this->dispatch('navigation-changed');
        Flux::toast(variant: 'success', text: $message);
    }

    public function render(): View
    {
        return view('livewire.navigation.list-dialog');
    }
}
