<?php

declare(strict_types=1);

namespace App\Livewire\Lists;

use App\Models\TaskList;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Services\Data\ListedTasks;
use App\Services\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The list page header (M2/D6) — list name plus active/completed counts as
 * quiet metadata, and (for a non-default list) a rename/move/delete menu.
 * List identity and its actions live here, split out of TaskPanel so each
 * component keeps a single UI concern (D6). Every menu item dispatches
 * `list-dialog-open` so Phase A's Navigation\ListDialog does the actual
 * work — rename/move/delete logic is never duplicated here.
 *
 * @property-read TaskList $list
 * @property-read ListedTasks $tasks
 */
class ListHeader extends Component
{
    #[Locked]
    public int $taskListId;

    public function mount(TaskListRepositoryInterface $taskLists): void
    {
        Gate::authorize('view', $this->resolveList($taskLists));
    }

    #[Computed]
    public function list(): TaskList
    {
        return $this->resolveList(app(TaskListRepositoryInterface::class));
    }

    #[Computed]
    public function tasks(): ListedTasks
    {
        return app(TaskService::class)->tasksFor($this->list);
    }

    /**
     * ListDialog dispatches navigation-changed after a rename (D6) — this
     * only busts the cached list/task counts so the header reflects it
     * immediately, it never re-dispatches (R5).
     */
    #[On('navigation-changed')]
    public function refresh(): void
    {
        unset($this->list, $this->tasks);
    }

    private function resolveList(TaskListRepositoryInterface $taskLists): TaskList
    {
        $list = $taskLists->findForUser($this->taskListId, Auth::user());

        abort_if($list === null, 403);

        return $list;
    }

    public function render(): View
    {
        return view('livewire.lists.list-header');
    }
}
