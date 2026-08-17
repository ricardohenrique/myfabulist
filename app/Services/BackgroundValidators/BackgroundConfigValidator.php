<?php

declare(strict_types=1);

namespace App\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;

/**
 * Strategy for validating/normalizing the type-specific config stored on
 * `users.workspace_background_config`. One implementation per catalog
 * `type` — `WorkspaceBackgroundService` picks the matching implementation
 * from a `type => validator` map, so adding a new background type never
 * requires editing an existing validator (Open/Closed).
 */
interface BackgroundConfigValidator
{
    /**
     * Validate the raw, type-tagged config and return the sanitized shape
     * that should actually be persisted.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidBackgroundSelectionException when the config does not
     *                                             match what this type requires.
     */
    public function validate(array $config): array;
}
