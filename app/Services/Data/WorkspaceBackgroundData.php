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
     * @param  bool  $isCustomized  True when `config` is the user's own stored
     *                              override (`users.workspace_background_config`
     *                              is non-null); false when it is only the
     *                              option's current `default_config`, followed
     *                              live. The picker UI uses this to decide
     *                              whether re-saving without editing anything
     *                              should stay live-linked to the preset or
     *                              keep the user's existing personal override.
     */
    public function __construct(
        public string $optionKey,
        public string $type,
        public array $config,
        public bool $isCustomized,
    ) {}
}
