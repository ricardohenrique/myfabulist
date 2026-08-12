<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Plan 1 ("Shared Lists and Collaboration"), Step 4: a task's authorization
 * is entirely list membership, not `tasks.user_id` — a shared list's tasks
 * are fully collaborative for every accepted member, with no owner-only
 * tier at the task level. `loadMissing('taskList')` (not the bare
 * `$task->taskList` magic accessor) because a route-bound `Task`
 * (`AppServiceProvider::configureTaskRouteBinding()`) never eager-loads
 * `taskList`, and this must stay safe under `Model::preventLazyLoading()`.
 * `TaskListPolicy` deciding "null" (a task whose list was itself
 * soft-deleted) resolves to a denial via `Gate::allows()`'s own null-model
 * handling — no special-casing needed here.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can('view', $task->loadMissing('taskList')->taskList);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
