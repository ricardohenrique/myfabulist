<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskList;
use App\Services\Data\ListedTasks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskList
 */
class TaskListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'position' => $this->position,
            'tasks_count' => $this->whenCounted('tasks'),
            // Plan 1 ("Shared Lists and Collaboration"), Step 7:
            // `accepted_members_count` is attached on the three read paths a
            // client actually uses to decide whether to render a
            // share-management affordance: the list index (`allForUser()`),
            // single-list `show()`, and `GET lists/{list}/tasks`
            // (`TaskListTaskController::index()`, via `withMemberCount()`) —
            // the endpoint a client sits on while viewing a list's contents.
            // It is gracefully absent (both keys omitted, same as
            // `tasks_count` already behaves inconsistently across read paths
            // today) everywhere else: `store()`/`update()`'s return paths
            // (a freshly built TaskList instance, not one resolved through a
            // counted query), `GET /inbox` (the Inbox can never be shared —
            // F9 — so the value would always be false/1 there anyway), the
            // nested `list` inside `TaskResource`, and the nested `lists`
            // inside `FolderResource`. These are a documented, deliberate
            // gap, not an oversight — revisit before Steps 9-10 need them.
            'member_count' => $this->whenCounted('accepted_members'),
            'is_shared' => $this->when(
                $this->accepted_members_count !== null,
                fn () => $this->accepted_members_count > 1,
            ),
            'is_owner' => $this->user_id === $request->user()?->id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder === null ? null : [
                'id' => $this->folder->id,
                'name' => $this->folder->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The shared "list + its tasks" envelope used by both /inbox and
     * /lists/{list}/tasks (D4 — the same read model, the same wire shape).
     *
     * @return array<string, mixed>
     */
    public static function withTasks(TaskList $taskList, ListedTasks $tasks): array
    {
        return [
            'list' => self::make($taskList),
            'tasks' => [
                'active' => TaskResource::collection($tasks->active),
                'completed' => TaskResource::collection($tasks->completed),
                'completed_count' => $tasks->completedCount,
            ],
        ];
    }
}
