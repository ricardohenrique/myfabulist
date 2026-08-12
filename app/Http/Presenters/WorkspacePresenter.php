<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\User;
use App\Services\Data\NavigationFolder;
use App\Services\NavigationService;
use App\Services\TaskService;

class WorkspacePresenter
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly TaskService $tasks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forList(User $user, TaskList $list): array
    {
        $listedTasks = $this->tasks->tasksFor($list, $user);

        return [
            ...$this->base($user, $list->is_default ? 'inbox' : 'list'),
            'currentList' => $this->list($list),
            'heading' => $list->name,
            'eyebrow' => $list->is_default
                ? 'Quick capture'
                : ($list->folder->name ?? 'Ungrouped list'),
            'canAddTask' => true,
            'tasks' => [
                ...$listedTasks->active->map(fn (Task $task): array => $this->task($task, $list))->all(),
                ...$listedTasks->completed->map(fn (Task $task): array => $this->task($task, $list))->all(),
            ],
            'completedCount' => $listedTasks->completedCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forStarred(User $user): array
    {
        $tasks = $this->tasks->starredFor($user);

        return [
            ...$this->base($user, 'starred'),
            'currentList' => null,
            'heading' => 'Starred',
            'eyebrow' => 'Important tasks',
            'canAddTask' => false,
            'tasks' => $tasks
                ->map(fn (Task $task): array => $this->task($task, $task->taskList))
                ->all(),
            'completedCount' => $tasks->where('is_completed', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function base(User $user, string $view): array
    {
        $navigation = $this->navigation->treeFor($user);

        return [
            'view' => $view,
            'inbox' => $this->list($navigation->inbox),
            'starredCount' => $navigation->starredCount,
            'folders' => array_map(
                fn (NavigationFolder $folder): array => [
                    'id' => $folder->folder->id,
                    'name' => $folder->folder->name,
                    'lists' => $folder->lists
                        ->map(fn (TaskList $list): array => $this->list($list))
                        ->all(),
                ],
                $navigation->folders,
            ),
            'ungroupedLists' => $navigation->ungroupedLists
                ->map(fn (TaskList $list): array => $this->list($list))
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, folderId: int|null, isDefault: bool, activeTaskCount: int}
     */
    private function list(TaskList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'folderId' => $list->folder_id,
            'isDefault' => $list->is_default,
            'activeTaskCount' => (int) ($list->active_tasks_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function task(Task $task, TaskList $list): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'note' => $task->note,
            'dueDate' => $task->due_date?->format('Y-m-d'),
            'dueDateLabel' => $this->dueDateLabel($task),
            'dueDateStatus' => $task->dueDateStatus(),
            // Task::$is_starred is honestly bool|null (Plan 1, Step 3 —
            // nothing today produces null, since route binding always
            // resolves the authenticated viewer before SubstituteBindings
            // runs, but the type can't promise that) — coerce the same
            // defensive way `activeTaskCount` already does above, since the
            // TypeScript prop and the V1 API contract both declare this a
            // plain boolean.
            'isStarred' => (bool) $task->is_starred,
            'completedAt' => $task->completed_at?->toIso8601String(),
            'createdAt' => $task->created_at?->toIso8601String(),
            'taskListId' => $task->task_list_id,
            'taskListName' => $list->name,
            'subtasks' => $task->subtasks
                ->map(fn (Subtask $subtask): array => [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'isCompleted' => $subtask->is_completed,
                    'createdAt' => $subtask->created_at?->toIso8601String(),
                ])
                ->all(),
            'comments' => $task->comments
                ->map(fn (TaskComment $comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'avatarUrl' => $comment->author->profile_photo_url,
                    ],
                    'createdAt' => $comment->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function dueDateLabel(Task $task): ?string
    {
        if ($task->due_date === null) {
            return null;
        }

        return match (true) {
            $task->due_date->isSameDay(today()) => 'Today',
            $task->due_date->isSameDay(today()->addDay()) => 'Tomorrow',
            $task->due_date->isSameDay(today()->subDay()) => 'Yesterday',
            default => $task->due_date->format('M j'),
        };
    }
}
