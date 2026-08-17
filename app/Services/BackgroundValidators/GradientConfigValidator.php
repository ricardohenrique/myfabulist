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

        return [
            'from' => strtolower($from),
            'to' => strtolower($to),
        ];
    }
}
