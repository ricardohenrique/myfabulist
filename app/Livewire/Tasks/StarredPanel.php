<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Concerns\HasUndoableTaskActions;
use App\Exceptions\TaskListNotFoundException;
use App\Models\Task;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\TaskListService;
use App\Services\TaskService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The user's starred tasks across all lists (S2) — a cross-list query, not
 * a list or folder. Every mutation calls TaskService in-process (D1); no
 * new service or repository methods beyond Step 7.
 *
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, TaskList> $lists
 */
class StarredPanel extends Component
{
    use HasUndoableTaskActions;

    public function mount(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return app(TaskService::class)->starredFor(Auth::user());
    }

    /**
     * The move-to-list picker (S6/Step 4), fetched once per render — never
     * per row.
     *
     * @return Collection<int, TaskList>
     */
    #[Computed]
    public function lists(): Collection
    {
        return app(TaskListService::class)->allFor(Auth::user());
    }

    /**
     * Unstarring removes the row from the view on the next render, since
     * starredFor() only returns is_starred = true tasks.
     */
    public function unstarTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $this->toggleStar($taskId, $tasks, $taskService);
    }

    /**
     * The shared <x-tasks.task-row> star button (D1) calls this method name
     * on whichever panel renders it. Every row on this page is already
     * starred, so toggling always unstars — unstarTask() is kept as an
     * explicit alias for callers that name the intent directly.
     */
    public function toggleStar(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');
        $wasStarred = $task->is_starred;

        $taskService->setStarred($task, false);

        $this->rememberLastAction('star', $task->id, ['wasStarred' => $wasStarred]);
    }

    public function completeTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');

        $taskService->complete($task);

        $this->rememberLastAction('complete', $task->id);
    }

    public function restoreTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');

        $taskService->restore($task);
    }

    /**
     * The row menu's "Move to…" submenu (S6/Step 4) — the fast path;
     * TaskDetails::save() is the deliberate path. Both call
     * TaskService::move() with position: null (D5), appending to the end
     * of the destination list.
     */
    public function moveTask(int $taskId, int $targetListId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');
        $destinationName = $this->lists->firstWhere('id', $targetListId)?->name;
        $fromListId = $task->task_list_id;

        try {
            $taskService->move($task, Auth::user(), $targetListId, null);
        } catch (TaskListNotFoundException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->rememberLastAction('move', $task->id, ['fromListId' => $fromListId]);

        $this->dispatch('tasks-changed');
        $this->dispatch('navigation-changed');
        Flux::toast(variant: 'success', text: __('Moved to :list.', ['list' => $destinationName ?? __('another list')]));
    }

    /**
     * The row menu's "Edit details" item (D1/D2) — opens the single
     * details flyout mounted once per page via a global event.
     */
    public function openDetails(int $taskId): void
    {
        $this->dispatch('task-details-open', taskId: $taskId);
    }

    /**
     * Fired by TaskDetails after a save/move/delete (D2/R5). Only busts the
     * computed cache — never re-dispatches (R5).
     */
    #[On('tasks-changed')]
    public function refresh(): void
    {
        unset($this->tasks);
    }

    protected function authorizedTask(int $taskId, TaskRepositoryInterface $tasks, string $ability): Task
    {
        $task = $tasks->findForUser($taskId, Auth::user());

        abort_if($task === null, 404);

        Gate::authorize($ability, $task);

        return $task;
    }

    public function render(): View
    {
        return view('livewire.tasks.starred-panel');
    }
}
