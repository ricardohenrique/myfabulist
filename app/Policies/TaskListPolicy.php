<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskList;
use App\Models\User;

class TaskListPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }

    /**
     * The default (Inbox) list can never be deleted. This is defence in
     * depth behind TaskListService::delete(), which throws
     * DefaultTaskListCannotBeDeletedException for the same rule (D5).
     */
    public function delete(User $user, TaskList $taskList): bool
    {
        if ($taskList->is_default) {
            return false;
        }

        return $user->id === $taskList->user_id;
    }
}
