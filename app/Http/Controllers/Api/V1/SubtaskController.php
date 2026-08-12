<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSubtaskRequest;
use App\Http\Resources\Api\V1\SubtaskResource;
use App\Models\Subtask;
use App\Services\SubtaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
    ) {}

    public function update(UpdateSubtaskRequest $request, Subtask $subtask): JsonResponse
    {
        $subtask = $this->subtasks->rename($subtask, $request->validated('title'));

        return SubtaskResource::make($subtask)->response();
    }

    public function destroy(Subtask $subtask): Response
    {
        $this->authorize('delete', $subtask);

        $this->subtasks->delete($subtask);

        return response()->noContent();
    }
}
