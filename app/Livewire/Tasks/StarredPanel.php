<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\TaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The user's starred tasks across all lists (S2) — a cross-list query, not
 * a list or folder. Every mutation calls TaskService in-process (D1); no
 * new service or repository methods beyond Step 7.
 *
 * @property-read Collection<int, Task> $tasks
 */
class StarredPanel extends Component
{
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
     * Unstarring removes the row from the view on the next render, since
     * starredFor() only returns is_starred = true tasks.
     */
    public function unstarTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks);

        $taskService->setStarred($task, false);
    }

    public function completeTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks);

        $taskService->complete($task);
    }

    public function restoreTask(int $taskId, TaskRepositoryInterface $tasks, TaskService $taskService): void
    {
        $task = $this->authorizedTask($taskId, $tasks);

        $taskService->restore($task);
    }

    private function authorizedTask(int $taskId, TaskRepositoryInterface $tasks): Task
    {
        $task = $tasks->findForUser($taskId, Auth::user());

        abort_if($task === null, 404);

        Gate::authorize('update', $task);

        return $task;
    }

    public function render(): View
    {
        return view('livewire.tasks.starred-panel');
    }
}
