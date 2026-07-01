<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A generic fallback for unhandled domain errors.
 * @message Sorry, something went wrong on the server. Please try again later.
 */
class ServerErrorException extends RuntimeException
{
    public function getStatusCode(): int
    {
        return 500;
    }

    public function getErrorCode(): string
    {
        return 'INTERNAL_ERROR';
    }
}
