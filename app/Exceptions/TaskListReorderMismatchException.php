<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * F16 (Plan 1, Step 6): this reorders folders/lists in the *acting user's own*
 * sidebar — folders are never shareable, and a list's position within one is
 * per-member placement (Architecture Decision 1), so another collaborator
 * can never be the cause of a mismatch here. The realistic conflict source
 * is the same user reordering from a second browser tab or another device.
 * `errorCode()` is unchanged so nothing an API client branches on shifts;
 * only the human-readable message is reworded.
 */
class TaskListReorderMismatchException extends DomainException
{
    public static function forCurrentContainer(): self
    {
        return new self('The list order changed since you last loaded it — perhaps from another tab or device. Refresh and try again.');
    }

    public function errorCode(): string
    {
        return 'task_list_reorder_mismatch';
    }
}
