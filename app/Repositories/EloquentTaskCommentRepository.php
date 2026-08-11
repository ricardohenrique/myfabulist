<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentTaskCommentRepository implements TaskCommentRepositoryInterface
{
    /**
     * @return Collection<int, TaskComment>
     */
    public function forTask(Task $task): Collection
    {
        return TaskComment::query()
            ->where('task_id', $task->id)
            ->with('author')
            ->oldest()
            ->orderBy('id')
            ->get();
    }

    public function create(Task $task, User $author, string $body): TaskComment
    {
        $comment = new TaskComment;

        $comment->forceFill([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'body' => $body,
        ])->save();

        return $comment->load('author');
    }
}
