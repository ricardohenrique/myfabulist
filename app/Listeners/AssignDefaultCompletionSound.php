<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\CompletionSoundService;
use Illuminate\Auth\Events\Registered;

class AssignDefaultCompletionSound
{
    public function __construct(
        private readonly CompletionSoundService $sounds,
    ) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->sounds->assignDefaultTo($event->user);
        }
    }
}
