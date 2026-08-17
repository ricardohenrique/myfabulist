<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\WorkspaceBackgroundService;
use Illuminate\Auth\Events\Registered;

/**
 * Puts the new user on the platform's default workspace background
 * immediately after registration, mirroring `CreateDefaultTaskList` — the
 * established pattern in this app for provisioning defaults on
 * registration. Demo/seeded/factory-created users never fire `Registered`,
 * so they simply keep `workspace_background_option_id` null (rendering
 * today's hard-coded CSS fallback) unless explicitly assigned one.
 *
 * Auto-discovered by Laravel's event discovery (Application::configure()
 * calls withEvents() by default) — the handle() type-hint on Registered is
 * all that is required, no manual Event::listen() registration.
 */
class AssignDefaultWorkspaceBackground
{
    public function __construct(
        private readonly WorkspaceBackgroundService $backgrounds,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->backgrounds->assignDefaultTo($event->user);
    }
}
