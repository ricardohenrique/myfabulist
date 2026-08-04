<?php

declare(strict_types=1);

namespace App\Services\Data;

use Illuminate\Support\Carbon;

/**
 * Input for TaskService::update() — replace semantics for all four fields
 * (D4). `title` is always required by the caller; `note`, `dueDate` and
 * `isStarred` being null/false clears them rather than leaving them
 * unchanged, removing the "null means unchanged vs. clear" ambiguity.
 */
final readonly class TaskDetailsData
{
    public function __construct(
        public string $title,
        public ?string $note,
        public ?Carbon $dueDate,
        public bool $isStarred,
    ) {}
}
