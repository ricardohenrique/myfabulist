<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderOrderRequest extends FormRequest
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
            'folder_ids' => ['required', 'array', 'min:1'],
            'folder_ids.*' => [
                'required',
                'integer',
                Rule::exists('folders', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function folderIds(): array
    {
        return $this->validated('folder_ids');
    }
}
