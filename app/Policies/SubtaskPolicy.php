<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 4: delegates to
 * `TaskPolicy` (which itself delegates to `TaskListPolicy`), the same
 * "any accepted member" collaborative model as tasks — a subtask has no
 * owner-only tier either. `loadMissing('task.taskList')` for the same
 * `Model::preventLazyLoading()` reason as `TaskPolicy` — an (implicitly
 * route-bound) `Subtask` never arrives with `task.taskList` eager-loaded.
 */
class SubtaskPolicy
{
    public function view(User $user, Subtask $subtask): bool
    {
        return $user->can('view', $subtask->loadMissing('task.taskList')->task);
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $this->view($user, $subtask);
    }

    public function delete(User $user, Subtask $subtask): bool
    {
        return $this->view($user, $subtask);
    }
}
