<?php

namespace App\Exceptions;

/**
 * Attempting to login or perform actions with a deactivated account.
 * @message Your account has been deactivated. Please contact support.
 */
class AccountDeactivatedException extends ServerErrorException
{
    public function __construct()
    {
        parent::__construct('Your account has been deactivated. Please contact support.');
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getErrorCode(): string
    {
        return 'ACCOUNT_DEACTIVATED';
    }
}
