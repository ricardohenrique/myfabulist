<?php

declare(strict_types=1);

namespace App\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;

class GradientConfigValidator implements BackgroundConfigValidator
{
    private const HEX_COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    public function validate(array $config): array
    {
        $from = $config['from'] ?? null;
        $to = $config['to'] ?? null;

        if (! is_string($from) || preg_match(self::HEX_COLOR_PATTERN, $from) !== 1
            || ! is_string($to) || preg_match(self::HEX_COLOR_PATTERN, $to) !== 1) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('gradient');
        }

        $sanitized = [
            'from' => strtolower($from),
            'to' => strtolower($to),
        ];

        foreach (['workspace_header', 'task_composer'] as $key) {
            $value = $this->validateOptionalColor($config, $key);

            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * `workspace_header`/`task_composer` are optional per-preset overrides
     * of the algorithmically-derived header/composer colors — validated
     * only when present (and not null), omitted from the sanitized result
     * otherwise rather than being forced to null.
     *
     * @param  array<string, mixed>  $config
     */
    private function validateOptionalColor(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match(self::HEX_COLOR_PATTERN, $value) !== 1) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('gradient');
        }

        return strtolower($value);
    }
}
