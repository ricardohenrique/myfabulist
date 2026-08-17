<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidBackgroundSelectionException;
use App\Models\User;
use App\Models\WorkspaceBackgroundOption;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceBackgroundOptionRepositoryInterface;
use App\Services\BackgroundValidators\BackgroundConfigValidator;
use App\Services\BackgroundValidators\FlatColorConfigValidator;
use App\Services\BackgroundValidators\GradientConfigValidator;
use App\Services\BackgroundValidators\ImageConfigValidator;
use App\Services\Data\WorkspaceBackgroundData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Owns the workspace-background preference lifecycle: which types a user may
 * currently choose from, and validating/persisting a new selection or a
 * reset back to "no preference". Per-type config validation is delegated to
 * a `BackgroundConfigValidator` (Strategy), keyed by the option's `type`
 * column below — adding a 4th background type means adding one validator
 * class plus one map entry here, never touching the branching logic itself
 * (Open/Closed, per the plan's Architecture Decision).
 *
 * `users.workspace_background_config` being `null` is load-bearing: it does
 * not mean "no config", it means "no personal override — follow the
 * option's current `default_config`". Adopting a preset unmodified stores
 * `null` rather than copying `default_config` onto the user's row, so a
 * later admin edit to the catalog's `default_config` is immediately visible
 * to every user who adopted that preset as-is (see `resolvedBackgroundFor()`
 * and `updateSelection()`). Only a user who submits their own config gets a
 * non-null, independently-stored value that a later `default_config` edit
 * cannot touch.
 */
class WorkspaceBackgroundService
{
    /** @var array<string, BackgroundConfigValidator> */
    private readonly array $validators;

    public function __construct(
        private readonly WorkspaceBackgroundOptionRepositoryInterface $options,
        private readonly UserRepositoryInterface $users,
        FlatColorConfigValidator $flatColorValidator,
        ImageConfigValidator $imageValidator,
        GradientConfigValidator $gradientValidator,
    ) {
        $this->validators = [
            'flat_color' => $flatColorValidator,
            'image' => $imageValidator,
            'gradient' => $gradientValidator,
        ];
    }

    /**
     * Enabled options, plus the user's current selection even when it has
     * since been disabled — a disabled type drops out of the pool offered
     * to everyone else, but a user already on it keeps seeing (and can keep
     * using) it, per the plan's functional requirements.
     *
     * @return Collection<int, WorkspaceBackgroundOption>
     */
    public function availableOptionsFor(User $user): Collection
    {
        $enabled = $this->options->enabled();
        $currentOptionId = $user->workspace_background_option_id;

        if ($currentOptionId === null) {
            return $enabled;
        }

        $alreadyIncluded = $enabled->contains(
            fn (WorkspaceBackgroundOption $option): bool => $option->id === $currentOptionId,
        );

        if ($alreadyIncluded) {
            return $enabled;
        }

        $current = $this->options->findById($currentOptionId);

        return $current === null ? $enabled : $enabled->push($current);
    }

    /**
     * The user's currently resolved background, ready for display. Null
     * means "no preference" — callers should fall back to today's
     * hard-coded defaults, never render an empty/broken background.
     *
     * When the user has an option selected but no personal config override
     * (`workspace_background_config` is `null`), this resolves live against
     * the option's current `default_config` rather than a value frozen at
     * selection time — an admin editing the catalog changes what every such
     * user sees on their very next render.
     */
    public function resolvedBackgroundFor(User $user): ?WorkspaceBackgroundData
    {
        if ($user->workspace_background_option_id === null) {
            return null;
        }

        $option = $this->options->findById($user->workspace_background_option_id);

        if ($option === null) {
            return null;
        }

        $config = $user->workspace_background_config ?? $option->default_config ?? [];

        return new WorkspaceBackgroundData(
            optionKey: $option->key,
            type: $option->type,
            config: $this->displayConfig($option->type, $config),
            isCustomized: $user->workspace_background_config !== null,
        );
    }

    /**
     * Validate and persist a new background selection for the user.
     *
     * A caller may submit a fully-formed config to customize the value —
     * validated and stored on the user's own row, exactly as before. Or an
     * empty config to simply adopt the option's own curated `default_config`
     * — this is how the picker's preset cards work: click "Aurora Waves"
     * and save, with no color/file input required. Adopting a preset this
     * way stores `null` on `workspace_background_config`, not a copy of
     * `default_config` — see the class docblock for why.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidBackgroundSelectionException
     */
    public function updateSelection(User $user, string $optionKey, array $config): User
    {
        $option = $this->options->findByKey($optionKey);

        if ($option === null) {
            throw InvalidBackgroundSelectionException::becauseUnknownKey($optionKey);
        }

        $isCurrentSelection = $user->workspace_background_option_id === $option->id;

        if (! $option->enabled && ! $isCurrentSelection) {
            throw InvalidBackgroundSelectionException::becauseDisabled($optionKey);
        }

        $user->forceFill([
            'workspace_background_option_id' => $option->id,
            'workspace_background_config' => $this->configToPersist($option, $config),
        ]);

        return $this->users->save($user);
    }

    /**
     * Reset the user back to "no preference" — the CSS fallback values then
     * render exactly as they do for a user who never set one.
     */
    public function clearSelection(User $user): User
    {
        $user->forceFill([
            'workspace_background_option_id' => null,
            'workspace_background_config' => null,
        ]);

        return $this->users->save($user);
    }

    /**
     * Resolves what actually gets persisted on `workspace_background_config`
     * for a selection:
     *
     * - A non-empty submitted config always wins: validated and returned as
     *   today (a user customizing their own value, stored independently of
     *   the option's `default_config`).
     * - An empty submitted config with an option `default_config` adopts
     *   the preset unmodified — but that is represented by storing `null`,
     *   not a copy of `default_config`, so `resolvedBackgroundFor()` keeps
     *   resolving it live against the option's current default. Nothing
     *   needs validating here: the value is never persisted to the user
     *   row.
     * - An empty submitted config with no `default_config` falls through to
     *   the validator with that empty config, which throws exactly as it
     *   did before this method existed — there is nothing to adopt and
     *   nothing was submitted.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     *
     * @throws InvalidBackgroundSelectionException
     */
    private function configToPersist(WorkspaceBackgroundOption $option, array $config): ?array
    {
        if ($config !== []) {
            return $this->validatorFor($option->type)->validate($config);
        }

        if ($option->default_config === null) {
            return $this->validatorFor($option->type)->validate($config);
        }

        return null;
    }

    private function validatorFor(string $type): BackgroundConfigValidator
    {
        return $this->validators[$type]
            ?? throw InvalidBackgroundSelectionException::becauseInvalidConfig($type);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function displayConfig(string $type, array $config): array
    {
        if ($type === 'image' && isset($config['path']) && is_string($config['path'])) {
            return ['url' => Storage::disk('public')->url($config['path'])];
        }

        return $config;
    }
}
