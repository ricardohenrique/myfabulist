<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Exceptions\DomainException;
use App\Models\Task;
use App\Models\TaskList;
use App\Repositories\Contracts\TaskRepositoryInterface;
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
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The task details flyout (D2) — one Livewire component mounted once per
 * page (inbox/starred/list), opened by any row's "Edit details" menu item
 * via the `task-details-open` event.
 *
 * TaskService::update() has replace semantics (D4): save() always builds a
 * complete TaskDetailsData from the form state that open() hydrated from
 * the task, never a partial one — a quick edit here can never silently
 * clear the note, the due date or the star.
 *
 * @property-read Collection<int, TaskList> $lists
 */
class TaskDetails extends Component
{
    public ?int $taskId = null;

    public string $title = '';

    public ?string $note = null;

    public ?string $dueDate = null;

    public bool $isStarred = false;

    public ?int $taskListId = null;

    #[On('task-details-open')]
    public function open(int $taskId, TaskRepositoryInterface $tasks): void
    {
        $this->resetErrorBag();

        $task = $this->authorizedTask($taskId, $tasks, 'view');

        $this->taskId = $task->id;
        $this->title = $task->title;
        $this->note = $task->note;
        $this->dueDate = $task->due_date?->format('Y-m-d');
        $this->isStarred = $task->is_starred;
        $this->taskListId = $task->task_list_id;

        Flux::modal('task-details')->show();
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

    public function save(TaskService $service, TaskRepositoryInterface $tasks): void
    {
        $this->title = trim($this->title);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'dueDate' => ['nullable', 'date'],
            'taskListId' => ['required', 'integer'],
        ]);

        $task = $this->authorizedTask($this->taskId, $tasks, 'update');

        try {
            $service->update($task, new TaskDetailsData(
                title: $this->title,
                note: $this->note === '' ? null : $this->note,
                dueDate: $this->parsedDueDate(),
                isStarred: $this->isStarred,
            ));

            // Applied after update() so a single save can rename and move
            // in one call (D5/Step 4) — $task->task_list_id still reflects
            // the pre-move list at this point.
            if ($this->taskListId !== $task->task_list_id) {
                $service->move($task, Auth::user(), $this->taskListId, null);
                $this->dispatch('navigation-changed');
            }
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->closeAndAnnounce(__('Task saved.'));
    }

    public function delete(TaskService $service, TaskRepositoryInterface $tasks): void
    {
        $task = $this->authorizedTask($this->taskId, $tasks, 'delete');

        $service->delete($task);

        $this->closeAndAnnounce(__('Task deleted.'));
    }

    private function parsedDueDate(): ?Carbon
    {
        return $this->dueDate === null || $this->dueDate === ''
            ? null
            : Carbon::parse($this->dueDate);
    }

    private function closeAndAnnounce(string $message): void
    {
        Flux::modal('task-details')->close();
        $this->taskId = null;
        $this->dispatch('tasks-changed');
        Flux::toast(variant: 'success', text: $message);
    }

    private function authorizedTask(?int $taskId, TaskRepositoryInterface $tasks, string $ability): Task
    {
        $task = $taskId === null ? null : $tasks->findForUser($taskId, Auth::user());

        abort_if($task === null, 404);

        Gate::authorize($ability, $task);

        return $task;
    }

    public function render(): View
    {
        return view('livewire.tasks.task-details');
    }
}
