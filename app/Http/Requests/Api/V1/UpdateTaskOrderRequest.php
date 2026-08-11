<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskOrderRequest extends FormRequest
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
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer'],
        ];
    }

    /**
     * Deliberately not scoped with Rule::exists like the folder/list order
     * requests: task reordering's id-set invariant (missing/extra/foreign
     * ids) is enforced by TaskRepository::applyOrder() as a domain
     * exception (TaskReorderMismatchException, D12/D13), not by validation,
     * so the same guarantee holds for any caller that skips this Form Request.
     *
     * @return array<int, int>
     */
    public function taskIds(): array
    {
        return $this->validated('task_ids');
    }
}
