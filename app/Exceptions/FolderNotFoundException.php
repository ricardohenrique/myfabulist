<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a referenced folder id does not exist or does not belong to
 * the acting user (D3) — cross-user reference injection is treated the same
 * as a missing row.
 */
class FolderNotFoundException extends DomainException
{
    public static function forId(int $folderId): self
    {
        return new self(sprintf('Folder [%d] was not found.', $folderId));
    }

    public function errorCode(): string
    {
        return 'folder_not_found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
