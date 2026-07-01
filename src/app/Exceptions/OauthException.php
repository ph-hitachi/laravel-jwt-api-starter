<?php

namespace App\Exceptions;

/**
 * OAuth authentication token validation failed.
 * @message OAuth authentication failed. Please check your credentials and try again.
 */
class OauthException extends ServerErrorException
{
    public function __construct(string $message = 'OAuth authentication failed. Please try again.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    public function getErrorCode(): string
    {
        return 'OAUTH_FAILED';
    }
}
