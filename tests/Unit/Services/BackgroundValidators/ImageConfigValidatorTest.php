<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;
use App\Services\BackgroundValidators\ImageConfigValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Boots the Laravel app (for the `Storage` facade) but touches no database —
 * placed under Unit for that reason, mirroring `UserProfilePhotoUrlTest`.
 */
class ImageConfigValidatorTest extends TestCase
{
    public function test_it_stores_a_valid_image_and_returns_its_path(): void
    {
        Storage::fake('public');
        $validator = new ImageConfigValidator;
        $file = UploadedFile::fake()->image('background.jpg', 800, 600);

        $result = $validator->validate(['image' => $file]);

        $this->assertArrayHasKey('path', $result);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_it_rejects_a_missing_image(): void
    {
        $validator = new ImageConfigValidator;

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate([]);
    }

    public function test_it_rejects_an_oversized_image(): void
    {
        Storage::fake('public');
        $validator = new ImageConfigValidator;
        $file = UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg');

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['image' => $file]);
    }

    public function test_it_rejects_a_non_image_mime_type(): void
    {
        $validator = new ImageConfigValidator;
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->expectException(InvalidBackgroundSelectionException::class);

        $validator->validate(['image' => $file]);
    }
}
