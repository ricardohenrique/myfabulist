<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Services\Data\NavigationFolder;
use App\Services\NavigationService;
use App\Services\TaskService;

class WorkspacePresenter
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly TaskService $tasks,
        private readonly TaskListMemberRepositoryInterface $members,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forList(User $user, TaskList $list): array
    {
        $listedTasks = $this->tasks->tasksFor($list, $user);

        return [
            ...$this->base($user, $list->is_default ? 'inbox' : 'list'),
            'currentList' => $this->sharingDetails($user, $list),
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
            'inbox' => $this->list($navigation->inbox, $user),
            'starredCount' => $navigation->starredCount,
            'folders' => array_map(
                fn (NavigationFolder $folder): array => [
                    'id' => $folder->folder->id,
                    'name' => $folder->folder->name,
                    'lists' => $folder->lists
                        ->map(fn (TaskList $list): array => $this->list($list, $user))
                        ->all(),
                ],
                $navigation->folders,
            ),
            'ungroupedLists' => $navigation->ungroupedLists
                ->map(fn (TaskList $list): array => $this->list($list, $user))
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, folderId: int|null, isDefault: bool, activeTaskCount: int, isShared: bool, isOwner: bool}
     */
    private function list(TaskList $list, User $user): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'folderId' => $list->folder_id,
            'isDefault' => $list->is_default,
            'activeTaskCount' => (int) ($list->active_tasks_count ?? 0),
            // Plan 1, Step 8: every list this method receives already
            // carries accepted_members_count — allForUser() (which feeds
            // every call site here except forList()'s own $list) attaches
            // it via withCount(). A list with only its owner accepted has a
            // count of 1, hence ">1", not ">0".
            'isShared' => ($list->accepted_members_count ?? 1) > 1,
            // Plan 1, Step 10: lets the sidebar decide Delete-vs-Leave per
            // row without needing the richer sharingDetails() shape below,
            // which only ever loads for the one list actually being viewed.
            'isOwner' => $list->user_id === $user->id,
        ];
    }

    /**
     * The richer, distinct shape for the one list the user is actually
     * looking at (`forList()`) — carries the member roster and
     * sharing-management flags on top of the plain sidebar shape every
     * other list gets from `list()` above.
     *
     * `isShared` here is deliberately derived from the roster this method
     * already loads (`count($members) > 1`), not `list()`'s own
     * `accepted_members_count`-based flag — `$list` reaches this method
     * from route-model-binding, which never populates that count (only
     * `allForUser()` and the dedicated `withAcceptedMemberCount()` seam do),
     * so `list()`'s flag would silently read `false` here regardless of
     * reality. Reusing `$members` instead of adding a second, separately
     * loaded count keeps there being exactly one query and exactly one
     * source of truth for "is this list shared" on this page — the
     * `...$this->list($list)` spread below still contributes every other
     * key, and this explicit `'isShared'` entry after it wins (later keys
     * override earlier ones in PHP array literals).
     *
     * @return array<string, mixed>
     */
    public function sharingDetails(User $user, TaskList $list): array
    {
        $isOwner = $list->user_id === $user->id;

        $members = $this->members->acceptedMembersFor($list)
            ->map(fn (TaskListMember $member): array => [
                'id' => $member->id,
                'userId' => $member->user->id,
                'name' => $member->user->name,
                'avatarUrl' => $member->user->profile_photo_url,
                // F18: email visible to the list owner only.
                'email' => $isOwner ? $member->user->email : null,
                'isOwner' => $member->user_id === $list->user_id,
            ])
            ->all();

        // Plan 1, Step 10 code review: gated to the owner only, and the
        // query is skipped entirely for anyone else — Q10(e) sanctioned
        // exposing *accepted* members to each other, never who else has
        // merely been invited and hasn't responded (they aren't a member
        // yet, and may still decline). Distinct from `pendingFor($user)`
        // (Step 8's notification center), which answers the opposite
        // question ("what has *this* user been invited to").
        $pendingInvitations = $isOwner
            ? $this->members->pendingInvitationsFor($list)
                ->map(fn (TaskListMember $member): array => [
                    'id' => $member->id,
                    'userId' => $member->user->id,
                    'name' => $member->user->name,
                    'avatarUrl' => $member->user->profile_photo_url,
                    'email' => $member->user->email,
                    'invitedAt' => $member->invited_at?->toIso8601String(),
                ])
                ->all()
            : [];

        return [
            // `isOwner` here is identical to this spread's own `isOwner`
            // (`$list->user_id === $user->id`) — no separate explicit key
            // needed.
            ...$this->list($list, $user),
            'isShared' => count($members) > 1,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canManageSharing' => $isOwner,
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
