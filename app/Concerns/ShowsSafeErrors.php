<?php

namespace App\Concerns;

use App\Exceptions\CapacityUnavailableException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turn a caught exception into text that is safe to show in the UI. Validation and capacity
 * messages are written for people; anything else (SQL, HTTP, gateway bodies) is reported and
 * replaced with a plain fallback so internals never reach the screen.
 */
trait ShowsSafeErrors
{
    protected function safeErrorMessage(Throwable $e, ?string $fallback = null): string
    {
        if ($e instanceof ValidationException) {
            return (string) collect($e->errors())->flatten()->first();
        }

        if ($e instanceof CapacityUnavailableException) {
            return $e->getMessage();
        }

        report($e);

        return $fallback ?? __('Something went wrong. Please try again in a few minutes.');
    }
}
