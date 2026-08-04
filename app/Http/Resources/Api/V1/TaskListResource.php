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
