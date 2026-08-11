<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidTaskCommentException;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TaskCommentService
{
    public function __construct(
        private readonly TaskCommentRepositoryInterface $comments,
    ) {}

    /**
     * @return Collection<int, TaskComment>
     */
    public function forTask(Task $task): Collection
    {
        return $this->comments->forTask($task);
    }

    public function create(Task $task, User $author, string $body): TaskComment
    {
        $body = trim($body);

        if ($body === '') {
            throw InvalidTaskCommentException::becauseBlank();
        }

        if (Str::length($body) > 65_535) {
            throw InvalidTaskCommentException::becauseTooLong();
        }

        return $this->comments->create($task, $author, $body);
    }
}
