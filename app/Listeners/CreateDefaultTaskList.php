<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\ProvisionNewAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Provisions the new user's Inbox and starter tasks immediately after
 * registration. This is the primary creation path; TaskListService::
 * inboxFor() remains the idempotent Inbox fallback for seeded or
 * factory-created users that never fire Registered.
 *
 * Auto-discovered by Laravel's event discovery (Application::configure()
 * calls withEvents() by default) — the handle() type-hint on Registered is
 * all that is required, no manual Event::listen() registration.
 */
class CreateDefaultTaskList
{
    public function __construct(
        private readonly ProvisionNewAccount $provisionAccount,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->provisionAccount->handle($event->user);
    }
}
