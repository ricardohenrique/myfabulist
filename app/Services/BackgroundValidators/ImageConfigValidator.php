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

    private const HEX_COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

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

        $sanitized = ['path' => $path];

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
     * of the fixed neutral scrim otherwise used for image backgrounds —
     * validated only when present (and not null), omitted from the
     * sanitized result otherwise rather than being forced to null. In
     * practice these two keys reach this validator only if a caller
     * bundles them alongside an actual file upload — an adopted `image`
     * preset's `default_config` (which is where these values normally
     * live) bypasses this validator entirely (see
     * `WorkspaceBackgroundService`).
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
            throw InvalidBackgroundSelectionException::becauseInvalidConfig('image');
        }

        return strtolower($value);
    }
}
