<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Models\Task;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends \App\Http\Requests\Api\V1\UpdateTaskRequest
{
    /**
     * `resources/js/layouts/app-shell.tsx` submits the task's *current*
     * `task_list_id` on every save, not only on an actual move — for a task
     * in a shared list, that id belongs to the list's owner, not
     * necessarily the acting (accepted, non-owner) member. Validating it as
     * an owned-list reference unconditionally would 422 an ordinary
     * title/note/due-date edit for any non-owner member (Plan 1, Step 4
     * code-review follow-up — a web/API parity break introduced by this
     * step's `TaskPolicy` widening, since the byte-identical
     * `PUT /api/v1/tasks/{task}` has no such rule).
     *
     * The ownership-scoped `exists` rule is therefore only applied when the
     * submission is a genuine attempt to move the task to a *different*
     * list — `TaskService::move()`'s `findOwnedBy()` (Q8) is the real
     * enforcement point for that cross-list boundary; this Form Request
     * only needs to keep the id valid at all for an actual move.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            ...parent::rules(),
            'task_list_id' => ['required', 'integer'],
        ];

        $task = $this->route('task');
        $submittedListId = $this->integer('task_list_id');

        if ($task instanceof Task && $submittedListId !== $task->task_list_id) {
            $rules['task_list_id'][] = Rule::exists('task_lists', 'id')
                ->where('user_id', $this->user()?->id)
                ->whereNull('deleted_at');
        }

        return $rules;
    }

    public function targetListId(): int
    {
        return (int) $this->validated('task_list_id');
    }
}
