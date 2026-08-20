<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TaskComment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TaskComment $comment,
    ) {}
}
