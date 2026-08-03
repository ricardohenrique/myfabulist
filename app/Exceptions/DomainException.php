<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base type for every business-rule violation raised by the Service layer.
 *
 * Concrete subclasses expose a named static constructor and a stable
 * `error_code` so the API can render a consistent JSON envelope
 * (see bootstrap/app.php's domain-exception renderer).
 */
abstract class DomainException extends RuntimeException
{
    /**
     * A stable, machine-readable identifier for this failure, exposed to API
     * clients as `error_code`.
     */
    abstract public function errorCode(): string;

    /**
     * The HTTP status this exception maps to when rendered as JSON.
     */
    public function httpStatus(): int
    {
        return 422;
    }
}
