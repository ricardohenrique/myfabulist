<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $this->user() !== null && $folder !== null && $this->user()->can('delete', $folder);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lists' => ['nullable', 'string', Rule::in(['detach', 'delete'])],
        ];
    }

    /**
     * The chosen strategy for the folder's lists, or null when none was
     * given (the service then throws FolderNotEmptyException on a
     * non-empty folder — this is the "confirm or move out first" rule).
     */
    public function listsStrategy(): ?string
    {
        return $this->validated('lists');
    }
}
