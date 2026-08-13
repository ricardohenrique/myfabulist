<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\TaskListMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 7: shared by both
 * `ListInvitationController::accept()` and `::decline()`. Authorization goes
 * through `TaskListMemberPolicy::respond()`, matching every other
 * model-bound API Form Request in this codebase (`$this->user()->can(...)`
 * against a real Policy method) rather than an inline check duplicated here.
 * `ListSharingService::{accept,decline}()` still re-check
 * `$membership->user_id !== $user->id` themselves and throw
 * `NotAMemberException` (422) as defence in depth — this request's job is
 * to reject a stranger with a proper 403 before the Service is ever reached.
 */
class RespondToInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invitation = $this->route('invitation');

        return $this->user() !== null
            && $invitation instanceof TaskListMember
            && $this->user()->can('respond', $invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
