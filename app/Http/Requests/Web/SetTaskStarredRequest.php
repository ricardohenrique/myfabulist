<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class SetTaskStarredRequest extends FormRequest
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
            'is_starred' => ['required', 'boolean'],
        ];
    }

    public function isStarred(): bool
    {
        return (bool) $this->validated('is_starred');
    }
}
