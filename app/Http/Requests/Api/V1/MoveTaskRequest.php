<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
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
            'task_list_id' => [
                'required',
                'integer',
                Rule::exists('task_lists', 'id')->where('user_id', $this->user()?->id),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function targetListId(): int
    {
        return (int) $this->validated('task_list_id');
    }

    public function position(): ?int
    {
        return $this->validated('position');
    }
}
