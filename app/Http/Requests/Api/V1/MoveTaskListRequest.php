<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $this->user() !== null && $list !== null && $this->user()->can('update', $list);
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
        ];
    }

    public function folderId(): ?int
    {
        $folderId = $this->validated('folder_id');

        return $folderId === null ? null : (int) $folderId;
    }
}
