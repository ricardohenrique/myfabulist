<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: invite a user by email
 * to `$this->route('list')`. Authorization is owner-only
 * (`TaskListPolicy::share()`, Q2) — `ListSharingService::invite()` re-checks
 * its own business rules (self-invite, already-member, cap, Inbox) as
 * defence in depth, but "is the caller even allowed to invite at all" is
 * this request's job.
 */
class StoreTaskListInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $this->user() !== null && $list !== null && $this->user()->can('share', $list);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
