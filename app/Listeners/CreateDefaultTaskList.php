<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\TaskListService;
use Illuminate\Auth\Events\Registered;

/**
 * Provisions the new user's Inbox immediately after registration (D5). This
 * is the primary creation path; TaskListService::inboxFor() is the
 * idempotent fallback for users who never fired Registered (e.g. seeded or
 * factory-created users).
 *
 * Auto-discovered by Laravel's event discovery (Application::configure()
 * calls withEvents() by default) — the handle() type-hint on Registered is
 * all that is required, no manual Event::listen() registration.
 */
class CreateDefaultTaskList
{
    public function __construct(
        private readonly TaskListService $taskListService,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->taskListService->inboxFor($event->user);
    }
}
