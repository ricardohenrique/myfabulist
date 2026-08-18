<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;

class ProvisionNewAccount
{
    /** @var array<int, string> */
    private const STARTER_TASK_TITLES = [
        'Add something you need to do',
        "Check this task when you're finished",
    ];

    public function __construct(
        private readonly TaskListService $taskLists,
        private readonly TaskService $tasks,
    ) {}

    /**
     * Create the permanent Inbox and its initial tasks as one workflow.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $inbox = $this->taskLists->inboxFor($user);

            // Quick capture inserts at position zero. Creating in reverse
            // preserves the product copy's intended top-to-bottom order.
            foreach (array_reverse(self::STARTER_TASK_TITLES) as $title) {
                $this->tasks->create($user, $inbox, $title);
            }
        });
    }
}
