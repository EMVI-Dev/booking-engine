<?php

namespace App\Services\Integrations;

use RuntimeException;

/**
 * Laravel Cloud answered but refused the request. The code is the HTTP status
 * (422 for validation errors such as an already-taken or invalid domain).
 */
class LaravelCloudException extends RuntimeException
{
    public function isValidationError(): bool
    {
        return $this->getCode() === 422;
    }
}
