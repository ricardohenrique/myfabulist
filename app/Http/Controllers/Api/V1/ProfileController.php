<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCompletionSoundRequest;
use App\Http\Requests\Api\V1\UpdateWorkspaceBackgroundRequest;
use App\Http\Resources\Api\V1\CompletionSoundResource;
use App\Http\Resources\Api\V1\WorkspaceBackgroundResource;
use App\Services\CompletionSoundService;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly WorkspaceBackgroundService $backgrounds,
        private readonly CompletionSoundService $sounds,
    ) {}

    /**
     * Calls the exact same `WorkspaceBackgroundService::updateSelection()`/
     * `clearSelection()` the Web `ProfileController::updateBackground()`
     * calls — this is a thin wrapper, not a second implementation of the
     * business rule.
     */
    public function updateBackground(UpdateWorkspaceBackgroundRequest $request): JsonResponse
    {
        $optionKey = $request->optionKey();

        $user = $optionKey === null
            ? $this->backgrounds->clearSelection($request->user())
            : $this->backgrounds->updateSelection($request->user(), $optionKey, $request->backgroundConfig());

        return WorkspaceBackgroundResource::make($this->backgrounds->resolvedBackgroundFor($user))->response();
    }

    public function updateCompletionSound(UpdateCompletionSoundRequest $request): JsonResponse
    {
        $user = $this->sounds->updateSelection($request->user(), $request->completionSoundKey());

        return CompletionSoundResource::make($this->sounds->resolvedSoundFor($user))->response();
    }
}
