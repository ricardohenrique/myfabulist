<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTaskOrderRequest;
use App\Models\TaskList;
use App\Services\TaskService;
use Illuminate\Http\Response;

class TaskOrderController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function __invoke(UpdateTaskOrderRequest $request, TaskList $list): Response
    {
        $this->tasks->reorder($list, $request->taskIds());

        return response()->noContent();
    }
}
