<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskListRepositoryInterface;

class TaskListService
{
    public function __construct(
        private readonly TaskListRepositoryInterface $taskLists,
    ) {}

    /**
     * The user's Inbox — always exists, never lives in a folder. Idempotent
     * and self-healing: creates the row on first access if the Registered
     * listener has not run for this user (D5). This is the single read path
     * every caller (web and API) must use to resolve the Inbox.
     */
    public function inboxFor(User $user): TaskList
    {
        return $this->taskLists->findDefaultFor($user)
            ?? $this->taskLists->createDefaultFor($user);
    }
}
