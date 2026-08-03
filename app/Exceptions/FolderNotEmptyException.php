<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Folder;

/**
 * Thrown when a folder holding at least one list is deleted without an
 * explicit `detach` or `delete` strategy for its lists (D6).
 */
class FolderNotEmptyException extends DomainException
{
    public static function for(Folder $folder): self
    {
        return new self(sprintf(
            'Folder "%s" still contains lists. Detach or delete them before deleting the folder.',
            $folder->name,
        ));
    }

    public function errorCode(): string
    {
        return 'folder_not_empty';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
