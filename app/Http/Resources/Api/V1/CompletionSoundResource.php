<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Services\Data\CompletionSoundData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompletionSoundData */
class CompletionSoundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
        ];
    }

    public function toResponse($request): JsonResponse
    {
        if ($this->resource === null) {
            return response()->json(['data' => null]);
        }

        return parent::toResponse($request);
    }
}
