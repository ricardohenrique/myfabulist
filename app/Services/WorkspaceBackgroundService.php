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

        return new WorkspaceBackgroundData(
            optionKey: $option->key,
            type: $option->type,
            config: $this->displayConfig($option->type, $user->workspace_background_config ?? []),
        );
    }

    /**
     * Validate and persist a new background selection for the user.
     *
     * A caller may submit a fully-formed config to customize the value (as
     * before), or an empty config to simply adopt the option's own curated
     * `default_config` — this is how the picker's preset cards work: click
     * "Aurora Waves" and save, with no color/file input required.
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

        $sanitizedConfig = $this->effectiveConfig($option, $config);

        $user->forceFill([
            'workspace_background_option_id' => $option->id,
            'workspace_background_config' => $sanitizedConfig,
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
     * Resolves what actually gets persisted for a selection:
     *
     * - A non-empty submitted config always wins and is validated as today
     *   (a user customizing their own value).
     * - An empty submitted config adopts the option's `default_config` when
     *   one exists. For `image`, that value is used as-is — it is
     *   admin-seeded/trusted and, unlike a real upload, was never going to
     *   pass `ImageConfigValidator` (which only accepts an `UploadedFile`).
     *   For `flat_color`/`gradient`, it is still run through the type's
     *   validator — cheap, and it guarantees shape correctness through one
     *   code path rather than two.
     * - An empty submitted config with no `default_config` falls through to
     *   the validator with that empty config, which throws exactly as it
     *   did before this method existed.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidBackgroundSelectionException
     */
    private function effectiveConfig(WorkspaceBackgroundOption $option, array $config): array
    {
        if ($config !== []) {
            return $this->validatorFor($option->type)->validate($config);
        }

        if ($option->default_config === null) {
            return $this->validatorFor($option->type)->validate($config);
        }

        if ($option->type === 'image') {
            return $option->default_config;
        }

        return $this->validatorFor($option->type)->validate($option->default_config);
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
