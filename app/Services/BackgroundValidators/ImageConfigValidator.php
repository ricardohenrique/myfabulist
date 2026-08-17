<?php

declare(strict_types=1);

namespace App\Services\BackgroundValidators;

use App\Exceptions\InvalidBackgroundSelectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Mirrors `users.profile_photo_path`'s storage convention: only the stored
 * disk path is persisted, never the raw upload. Re-validates mime/size
 * itself rather than trusting the Form Request's rules alone — the service
 * layer is the single source of truth for what a valid config looks like
 * (Plan §Risk Assessment mitigation), since it can also be reached directly
 * (e.g. from a future console command or a second delivery mechanism) that
 * never passed through HTTP validation at all.
 */
class ImageConfigValidator implements BackgroundConfigValidator
{
    private const MAX_KILOBYTES = 5120;

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const STORAGE_DIRECTORY = 'workspace-backgrounds';

    public function validate(array $config): array
    {
        $image = $config['image'] ?? null;

        if (! $image instanceof UploadedFile || ! $image->isValid()) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        if (! str_starts_with((string) $image->getMimeType(), 'image/')) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        if (! in_array(strtolower((string) $image->extension()), self::ALLOWED_EXTENSIONS, true)) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        $size = $image->getSize();

        if ($size === false || $size > self::MAX_KILOBYTES * 1024) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        $path = Storage::disk('public')->putFile(self::STORAGE_DIRECTORY, $image);

        if (! is_string($path)) {
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        return ['path' => $path];
    }
}
