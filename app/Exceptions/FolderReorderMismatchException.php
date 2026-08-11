<?php

declare(strict_types=1);

namespace App\Exceptions;

class FolderReorderMismatchException extends DomainException
{
    public static function forCurrentFolders(): self
    {
        return new self('The submitted folder order no longer matches your current folders. Refresh and try again.');
    }

    public function errorCode(): string
    {
        return 'folder_reorder_mismatch';
    }
}
