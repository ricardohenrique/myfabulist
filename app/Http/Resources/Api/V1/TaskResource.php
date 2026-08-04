<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_list_id' => $this->task_list_id,
            'title' => $this->title,
            'note' => $this->note,
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_starred' => $this->is_starred,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'position' => $this->position,
            'list' => $this->whenLoaded('taskList', fn () => TaskListResource::make($this->taskList)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
