<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TaskComment $comment,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $comment = $this->comment->loadMissing(['author', 'task.taskList']);

        return [
            'kind' => 'task_comment',
            'comment_id' => $comment->id,
            'body' => Str::limit($comment->body, 240),
            'task' => [
                'id' => $comment->task->id,
                'title' => $comment->task->title,
            ],
            'list' => [
                'id' => $comment->task->taskList->id,
                'name' => $comment->task->taskList->name,
            ],
            'actor' => [
                'id' => $comment->author->id,
                'name' => $comment->author->name,
                'avatar_url' => $comment->author->profile_photo_url,
            ],
        ];
    }
}
