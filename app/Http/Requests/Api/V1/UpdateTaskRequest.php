<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Data\TaskDetailsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user() !== null && $task !== null && $this->user()->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'note' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'is_starred' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
        ]);
    }

    /**
     * PUT /tasks/{task} has replace semantics: title is required, the other
     * three fields are nullable and null clears them (D4).
     */
    public function toData(): TaskDetailsData
    {
        $dueDate = $this->validated('due_date');

        return new TaskDetailsData(
            title: $this->validated('title'),
            note: $this->validated('note'),
            dueDate: $dueDate !== null ? Carbon::parse($dueDate) : null,
            isStarred: (bool) $this->validated('is_starred'),
        );
    }
}
