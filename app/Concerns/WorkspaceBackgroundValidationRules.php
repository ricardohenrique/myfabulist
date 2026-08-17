<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared between the Web and API `UpdateWorkspaceBackgroundRequest`s,
 * mirroring the `ProfileValidationRules` pattern. `option_key` existence is
 * checked here (the HTTP-input-shape concern); whether that option is
 * currently *enabled* is a business rule left to
 * `WorkspaceBackgroundService::updateSelection()`.
 *
 * `color`/`gradient_from`/`gradient_to`/`image` are all `nullable` rather
 * than `required_if:option_key,<type>` — the catalog now has more than one
 * `key` per `type` (e.g. `flat_color_amethyst`, `flat_color_terracotta`),
 * so a `required_if` comparing the submitted `option_key` against a literal
 * type string could never fire correctly without a database lookup at the
 * HTTP-validation layer. More importantly, whether a field is *required*
 * now genuinely depends on data this layer shouldn't own: a preset card can
 * be selected and saved with none of these fields submitted at all, and
 * `WorkspaceBackgroundService::updateSelection()` adopts the option's own
 * `default_config` — the business-rule layer's own defence (see
 * `InvalidBackgroundSelectionException::becauseInvalidConfig()`) already
 * throws when a config is both empty and default-less, so nothing here
 * needs to duplicate that requirement.
 *
 * @phpstan-require-extends FormRequest
 */
trait WorkspaceBackgroundValidationRules
{
    private const HEX_COLOR_REGEX = '/^#[0-9a-f]{6}$/i';

    /**
     * @return array<string, array<int, ValidationRule|Exists|array<mixed>|string>>
     */
    protected function workspaceBackgroundRules(): array
    {
        return [
            'option_key' => $this->optionKeyRules(),
            'color' => $this->colorRules(),
            'gradient_from' => $this->gradientColorRules(),
            'gradient_to' => $this->gradientColorRules(),
            'image' => $this->imageRules(),
        ];
    }

    /**
     * Null clears the user's selection back to "no preference" — the
     * default-fallback CSS then applies (Plan, Step 6).
     *
     * @return array<int, ValidationRule|Exists|array<mixed>|string>
     */
    protected function optionKeyRules(): array
    {
        return ['nullable', 'string', Rule::exists('workspace_background_options', 'key')];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function colorRules(): array
    {
        return ['nullable', 'string', 'regex:'.self::HEX_COLOR_REGEX];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function gradientColorRules(): array
    {
        return ['nullable', 'string', 'regex:'.self::HEX_COLOR_REGEX];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function imageRules(): array
    {
        return ['nullable', 'image', 'max:5120'];
    }

    /**
     * Assemble the raw, per-type config the matching
     * `BackgroundConfigValidator` expects — only the fields that were
     * actually submitted are present; nothing is passed through as null
     * noise. Adding a new background type only ever means adding its own
     * field(s) here and to `workspaceBackgroundRules()`, never editing an
     * existing type's handling.
     *
     * @return array<string, mixed>
     */
    protected function workspaceBackgroundConfig(): array
    {
        return array_filter([
            'color' => $this->input('color'),
            'from' => $this->input('gradient_from'),
            'to' => $this->input('gradient_to'),
            'image' => $this->file('image'),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
