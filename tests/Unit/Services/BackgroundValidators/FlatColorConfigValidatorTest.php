<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;
use App\Services\BackgroundValidators\FlatColorConfigValidator;
use PHPUnit\Framework\TestCase;

class FlatColorConfigValidatorTest extends TestCase
{
    public function test_it_accepts_a_valid_hex_color_and_lowercases_it(): void
    {
        $validator = new FlatColorConfigValidator;

        $result = $validator->validate(['color' => '#AABBCC']);

        $this->assertSame(['color' => '#aabbcc'], $result);
    }

    public function test_it_rejects_a_missing_color(): void
    {
        $validator = new FlatColorConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate([]);
    }

    public function test_it_rejects_a_malformed_color(): void
    {
        $validator = new FlatColorConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['color' => 'not-a-color']);
    }

    public function test_it_rejects_a_config_shape_from_a_different_type(): void
    {
        $validator = new FlatColorConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['from' => '#112233', 'to' => '#445566']);
    }
}
