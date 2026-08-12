<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use App\Services\SubtaskService;
use Illuminate\Http\RedirectResponse;

class CompleteSubtaskController extends Controller
{
    public function __construct(private readonly SubtaskService $subtasks) {}

    public function __invoke(Subtask $subtask): RedirectResponse
    {
        $this->authorize('update', $subtask);
        $this->subtasks->complete($subtask);

        return back()->with('success', 'Subtask completed.');
    }
}
