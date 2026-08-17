<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;
use App\Services\BackgroundValidators\GradientConfigValidator;
use PHPUnit\Framework\TestCase;

class GradientConfigValidatorTest extends TestCase
{
    public function test_it_accepts_two_valid_hex_colors_and_lowercases_them(): void
    {
        $validator = new GradientConfigValidator;

        $result = $validator->validate(['from' => '#AABBCC', 'to' => '#112233']);

        $this->assertSame(['from' => '#aabbcc', 'to' => '#112233'], $result);
    }

    public function test_it_rejects_a_missing_to_color(): void
    {
        $validator = new GradientConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['from' => '#aabbcc']);
    }

    public function test_it_rejects_a_malformed_from_color(): void
    {
        $validator = new GradientConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['from' => 'not-a-color', 'to' => '#112233']);
    }

    public function test_it_rejects_a_config_shape_from_a_different_type(): void
    {
        $validator = new GradientConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['color' => '#112233']);
    }
}
