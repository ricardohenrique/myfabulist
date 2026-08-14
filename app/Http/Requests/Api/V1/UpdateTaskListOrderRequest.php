<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskListOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->where('user_id', $this->user()?->id),
            ],
            'task_list_ids' => ['required', 'array', 'min:1'],
            'task_list_ids.*' => [
                'required',
                'integer',
                // Scoped to the acting user's accepted membership, not
                // ownership (Plan 1, Step 4 code-review follow-up) —
                // allForUser() (which now includes shared lists, not just
                // owned ones) feeds the very tree this reorders, so an
                // ownership-only rule would reject a shared list's id from
                // every non-owner member's reorder payload while the
                // repository's "complete id set" validation simultaneously
                // rejects *omitting* it, making that container permanently
                // unreorderable for anyone but the owner. A trashed list's
                // membership row still exists (soft-deleting a list never
                // touches task_list_members), so the trashed-list exclusion
                // this rule used to provide directly (R4/Plan 4) is now
                // enforced one layer down, by applyOrder()'s own
                // whereHas('taskList', ...) — which already excludes a
                // trashed list from the "current ids" set and rejects the
                // payload as a mismatch (TaskListReorderMismatchException)
                // instead, still surfacing as a 422.
                Rule::exists('task_list_members', 'task_list_id')
                    ->where('user_id', $this->user()?->id)
                    ->where('status', 'accepted'),
            ],
        ];
    }

    public function folderId(): ?int
    {
        return $this->validated('folder_id');
    }

    /**
     * @return array<int, int>
     */
    public function taskListIds(): array
    {
        return $this->validated('task_list_ids');
    }
}
