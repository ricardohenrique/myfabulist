<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\Data\WorkspaceBackgroundData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceBackgroundData
 */
class WorkspaceBackgroundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'optionKey' => $this->optionKey,
            'type' => $this->type,
            'config' => $this->config,
            'isCustomized' => $this->isCustomized,
        ];
    }

    /**
     * Null when the user has no preference set — the response envelope is
     * then `{"data": null}`, not an error; "no background" is a valid,
     * successful state. `JsonResource::resolve()` would otherwise
     * `(array) null`-cast a null resource into `{"data": []}`, so this is
     * handled here rather than in `toArray()`, which is never reached for a
     * null resource in the first place.
     */
    public function toResponse($request): JsonResponse
    {
        if ($this->resource === null) {
            return response()->json(['data' => null]);
        }

        return parent::toResponse($request);
    }
}
