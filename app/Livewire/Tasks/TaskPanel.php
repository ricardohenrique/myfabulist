<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Exceptions\InvalidTaskTitleException;
use App\Models\Task;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Data\ListedTasks;
use App\Services\TaskService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
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
 */
class TaskPanel extends Component
{
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

        $taskService->setStarred($task, ! $task->is_starred);
    }

    private function resolveList(TaskListRepositoryInterface $taskLists): TaskList
    {
        $list = $taskLists->findForUser($this->taskListId, Auth::user());

        abort_if($list === null, 403);

        return $list;
    }

    private function authorizedTask(int $taskId, TaskRepositoryInterface $tasks, string $ability): Task
    {
        $task = $tasks->findForUser($taskId, Auth::user());

        abort_if($task === null, 404);

        Gate::authorize($ability, $task);

        return $task;
    }

    public function render(): View
    {
        return view('livewire.tasks.task-panel');
    }
}
