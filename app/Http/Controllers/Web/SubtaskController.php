<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateSubtaskRequest;
use App\Models\Subtask;
use App\Services\SubtaskService;
use Illuminate\Http\RedirectResponse;

class SubtaskController extends Controller
{
    public function __construct(
        private readonly SubtaskService $subtasks,
    ) {}

    public function update(UpdateSubtaskRequest $request, Subtask $subtask): RedirectResponse
    {
        $this->subtasks->rename($subtask, $request->validated('title'));

        return back()->with('success', 'Subtask updated.');
    }

    public function destroy(Subtask $subtask): RedirectResponse
    {
        $this->authorize('delete', $subtask);

        $this->subtasks->delete($subtask);

        return back()->with('success', 'Subtask deleted.');
    }
}
