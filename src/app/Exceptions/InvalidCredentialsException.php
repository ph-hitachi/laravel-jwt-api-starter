<?php

namespace App\Exceptions;

/**
 * Providing an incorrect password during login.
 * @message The email or password you entered is incorrect.
 */
class InvalidCredentialsException extends ServerErrorException
{
    public function __construct()
    {
        parent::__construct('The email or password you entered is incorrect.');
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    public function getErrorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }
}
