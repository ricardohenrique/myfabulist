<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubtaskResource;
use App\Models\Subtask;
use App\Services\SubtaskService;
use Illuminate\Http\JsonResponse;

class CompleteSubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
    ) {}

    /**
     * Idempotent command: completing an already-completed subtask is a no-op.
     */
    public function __invoke(Subtask $subtask): JsonResponse
    {
        $this->authorize('update', $subtask);

        return SubtaskResource::make($this->subtasks->complete($subtask))->response();
    }
}
