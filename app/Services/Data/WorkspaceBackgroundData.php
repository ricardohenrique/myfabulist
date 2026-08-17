<?php

declare(strict_types=1);

namespace App\Services\Data;

/**
 * A user's workspace background, resolved for display: the option's stable
 * `key`/`type` plus a display-ready config — the one place a stored image
 * path is turned into a public URL (`WorkspaceBackgroundService::
 * resolvedBackgroundFor()`), so neither delivery mechanism has to know the
 * storage convention behind it.
 */
final readonly class WorkspaceBackgroundData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $optionKey,
        public string $type,
        public array $config,
    ) {}
}
