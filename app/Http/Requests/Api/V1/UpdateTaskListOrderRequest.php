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
                Rule::exists('task_lists', 'id')->where('user_id', $this->user()?->id),
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
