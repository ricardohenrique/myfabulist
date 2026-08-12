<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;

class SubtaskPolicy
{
    public function view(User $user, Subtask $subtask): bool
    {
        return $user->id === $subtask->task->user_id;
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $user->id === $subtask->task->user_id;
    }

    public function delete(User $user, Subtask $subtask): bool
    {
        return $user->id === $subtask->task->user_id;
    }
}
