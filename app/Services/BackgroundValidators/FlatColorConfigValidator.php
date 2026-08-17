<?php

declare(strict_types=1);

namespace App\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;

class FlatColorConfigValidator implements BackgroundConfigValidator
{
    private const HEX_COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    public function validate(array $config): array
    {
        $color = $config['color'] ?? null;

        if (! is_string($color) || preg_match(self::HEX_COLOR_PATTERN, $color) !== 1) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('flat_color');
        }

        return ['color' => strtolower($color)];
    }
}
