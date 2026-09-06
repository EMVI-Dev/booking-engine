<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when a booking cannot be placed because the requested date is at or over
 * its daily capacity for one of the underlying activities.
 */
class CapacityUnavailableException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $remaining = 0,
        public readonly ?string $limitingProductTitle = null,
    ) {
        parent::__construct($message);
    }
}
