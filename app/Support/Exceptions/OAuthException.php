<?php

namespace App\Support\Exceptions;

use App\Support\Exceptions\GraphApiException;
use Symfony\Component\HttpFoundation\Response;

class OAuthException extends GraphApiException
{
    /**
     * Localization key prefix for error messages.
     *
     * @var string
     */
    protected $localize = "auth";

    /**
     * Create a new OAuthException instance.
     *
     * @param string|null $message The exception message.
     * @param string|null $code The error code.
     * @param array $data Additional data for translation.
     * @param int|null $statusCode The HTTP status code.
     */
    public function __construct(
        string $message = null, 
        string $code = null, 
        array $data = [], 
        Response|int $statusCode = Response::HTTP_BAD_REQUEST
    ){
        // Call the parent constructor with necessary parameters.
        parent::__construct(
            code: $code,
            message: $message ?? trans("$this->localize.$code", $data), // Use localization for the message.
            statusCode: $statusCode, // Default status code if not provided.
        );
    }

    public function report()
    {
        // Log or report the exception if necessary
    }
}

