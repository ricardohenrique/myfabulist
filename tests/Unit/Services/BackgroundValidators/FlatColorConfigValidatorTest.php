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

    public function test_it_accepts_and_lowercases_optional_workspace_header_and_task_composer_colors(): void
    {
        $validator = new FlatColorConfigValidator;

        $result = $validator->validate([
            'color' => '#aabbcc',
            'workspace_header' => '#AABBCC',
            'task_composer' => '#DDEEFF',
        ]);

        $this->assertSame([
            'color' => '#aabbcc',
            'workspace_header' => '#aabbcc',
            'task_composer' => '#ddeeff',
        ], $result);
    }

    public function test_it_omits_workspace_header_and_task_composer_when_absent_or_null(): void
    {
        $validator = new FlatColorConfigValidator;

        $result = $validator->validate(['color' => '#aabbcc', 'workspace_header' => null]);

        $this->assertSame(['color' => '#aabbcc'], $result);
    }

    public function test_it_rejects_a_malformed_optional_workspace_header_color(): void
    {
        $validator = new FlatColorConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['color' => '#aabbcc', 'workspace_header' => 'not-a-color']);
    }
}
