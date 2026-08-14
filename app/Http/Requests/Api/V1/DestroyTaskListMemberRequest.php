<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: revoke `{user}`'s
 * membership on `$this->route('list')`. Owner-only
 * (`TaskListPolicy::manageMembers()`, Q2) — `ListSharingService::revoke()`
 * re-checks ownership and the "the owner's own row can never be the target"
 * rule as defence in depth.
 */
class DestroyTaskListMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $this->user() !== null && $list !== null && $this->user()->can('manageMembers', $list);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
