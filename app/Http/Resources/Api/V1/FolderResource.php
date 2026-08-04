<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Folder
 */
class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            // Wire name stays "lists" even though the relation is
            // "taskLists" — the user-facing vocabulary is "list" (D2).
            'lists' => TaskListResource::collection($this->whenLoaded('taskLists')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
