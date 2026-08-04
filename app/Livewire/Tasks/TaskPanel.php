<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Concerns\HasUndoableTaskActions;
use App\Concerns\ReordersIds;
use App\Exceptions\InvalidTaskTitleException;
use App\Exceptions\TaskListNotFoundException;
use App\Exceptions\TaskReorderMismatchException;
use App\Models\Task;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Data\ListedTasks;
use App\Services\Data\TaskDetailsData;
use App\Services\TaskListService;
use App\Services\TaskService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A single list's task panel: quick capture, active/completed sections,
 * complete/restore, inline rename, delete, star toggle. Written generically
 * against a list id (not hard-coded to the Inbox) so Phase 2 can reuse it
 * for any list page.
 *
 * Every mutation calls TaskService in-process (D1) — no HTTP, no direct
 * Eloquent queries. Enforced by tests/Feature/Architecture/LayeringTest.php.
 *
 * @property-read TaskList $list
 * @property-read ListedTasks $tasks
 * @property-read Collection<int, TaskList> $lists
 */
class TaskPanel extends Component
{
    use HasUndoableTaskActions;
    use ReordersIds;

    #[Locked]
    public int $taskListId;

    public string $newTaskTitle = '';

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
     * Quick capture (M3): add a task and clear the input so the next task
     * can be added without leaving the keyboard.
     */
    public function addTask(TaskService $taskService): void
    {
        Gate::authorize('view', $this->list);

        try {
            $taskService->create(Auth::user(), $this->list, $this->newTaskTitle);
            $this->newTaskTitle = '';
            $this->resetErrorBag('newTaskTitle');
        } catch (InvalidTaskTitleException $e) {
            $this->addError('newTaskTitle', $e->getMessage());
        }
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

    public function renameTask(int $taskId, string $title, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');

        try {
            $taskService->rename($task, $title);
        } catch (InvalidTaskTitleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function deleteTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'delete');

        $taskService->delete($task);
    }

    public function toggleStar(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');
        $wasStarred = $task->is_starred;

        $taskService->setStarred($task, ! $wasStarred);

        $this->rememberLastAction('star', $task->id, ['wasStarred' => $wasStarred]);
    }

    /**
     * Row menu quick due-date actions (S1). TaskService::update() has
     * replace semantics (D4), so the task is re-read first and its
     * existing note/star pass through a *complete* TaskDetailsData — this
     * is the guarantee that a quick action can never silently wipe the
     * note or the star (R1).
     */
    public function setDueDate(int $taskId, ?string $date, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks, 'update');

        $taskService->update($task, new TaskDetailsData(
            title: $task->title,
            note: $task->note,
            dueDate: $date === null ? null : Carbon::parse($date),
            isStarred: $task->is_starred,
        ));
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
     * Move-up button (S4/Step 1, keyboard/pointer-agnostic path). A no-op
     * when the task is already first — there is nothing above it to swap
     * with, and no reorder call is made.
     */
    public function moveTaskUp(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $this->authorizedTask($taskId, $tasks, 'update');

        $ids = $this->activeTaskIds();
        $index = array_search($taskId, $ids, true);

        if ($index === false || $index === 0) {
            return;
        }

        $this->applyReorder($taskService, $ids, $taskId, $index - 1);
    }

    /**
     * Move-down button (S4/Step 1). A no-op when the task is already last.
     */
    public function moveTaskDown(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $this->authorizedTask($taskId, $tasks, 'update');

        $ids = $this->activeTaskIds();
        $index = array_search($taskId, $ids, true);

        if ($index === false || $index === count($ids) - 1) {
            return;
        }

        $this->applyReorder($taskService, $ids, $taskId, $index + 1);
    }

    /**
     * The drag entry point (S4/Step 2). `wire:sort` hands the moved task id
     * and its new DOM index directly (C2), so this skips the array_search
     * the button actions do and reuses the same reorderedIds()/reorder()
     * path (C1).
     */
    public function reorderTask(int $taskId, int $position, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $this->authorizedTask($taskId, $tasks, 'update');

        $ids = $this->activeTaskIds();

        $this->applyReorder($taskService, $ids, $taskId, $position);
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

    private function resolveList(TaskListRepositoryInterface $taskLists): TaskList
    {
        $list = $taskLists->findForUser($this->taskListId, Auth::user());

        abort_if($list === null, 403);

        return $list;
    }

    protected function authorizedTask(int $taskId, TaskRepositoryInterface $tasks, string $ability): Task
    {
        $task = $tasks->findForUser($taskId, Auth::user());

        abort_if($task === null, 404);

        Gate::authorize($ability, $task);

        return $task;
    }

    /**
     * The current active order, as plain ids — the shape reorderedIds()
     * and TaskService::reorder() both work with (C2).
     *
     * @return array<int, int>
     */
    private function activeTaskIds(): array
    {
        return $this->tasks->active
            ->map(fn (Task $task): int => $task->id)
            ->values()
            ->all();
    }

    /**
     * Shared by the button (Step 1) and drag (Step 2) entry points (C1/C2).
     *
     * The computed tasks() cache is always busted afterwards, success or
     * failure: activeTaskIds() above already forced it to compute (and
     * cache) the pre-reorder order, so a bare success would otherwise
     * re-render that stale snapshot instead of the just-written order. On a
     * caught TaskReorderMismatchException nothing was written (R6), and
     * busting the cache plus a friendly toast is exactly the "snap back to
     * the persisted truth" behaviour C3/R1 specifies for the drag path —
     * this is the same guarantee, just needed on both outcomes here.
     *
     * @param  array<int, int>  $ids
     */
    private function applyReorder(TaskService $taskService, array $ids, int $taskId, int $position): void
    {
        try {
            $taskService->reorder($this->list, $this->reorderedIds($ids, $taskId, $position));
        } catch (TaskReorderMismatchException) {
            Flux::toast(variant: 'danger', text: __('That list changed — refreshing.'));
        } finally {
            unset($this->tasks);
        }
    }

    public function render(): View
    {
        return view('livewire.tasks.task-panel');
    }
}
