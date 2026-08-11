<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Validation\Rule;

class UpdateTaskRequest extends \App\Http\Requests\Api\V1\UpdateTaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'task_list_id' => [
                'required',
                'integer',
                Rule::exists('task_lists', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function targetListId(): int
    {
        return (int) $this->validated('task_list_id');
    }
}
