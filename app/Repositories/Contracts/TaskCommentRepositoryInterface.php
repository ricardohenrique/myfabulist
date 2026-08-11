<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface TaskCommentRepositoryInterface
{
    /**
     * @return Collection<int, TaskComment>
     */
    public function forTask(Task $task): Collection;

    public function create(Task $task, User $author, string $body): TaskComment;
}
