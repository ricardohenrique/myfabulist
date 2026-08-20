<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskCommentCreated;
use App\Models\TaskListMember;
use App\Notifications\TaskCommentNotification;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class SendTaskCommentNotifications implements ShouldQueueAfterCommit
{
    public function __construct(
        private readonly TaskListMemberRepositoryInterface $members,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function handle(TaskCommentCreated $event): void
    {
        $comment = $event->comment->loadMissing('author');
        $task = $this->tasks->findWithTaskList($comment->task_id);

        if ($task === null) {
            return;
        }

        $comment->setRelation('task', $task);

        $this->members->acceptedMembersFor($task->taskList)
            ->reject(fn (TaskListMember $membership): bool => $membership->user_id === $comment->user_id)
            ->each(fn (TaskListMember $membership) => $membership->user->notify(new TaskCommentNotification($comment)));
    }
}
