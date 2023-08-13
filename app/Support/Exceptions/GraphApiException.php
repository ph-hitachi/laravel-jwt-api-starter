<?php

namespace App\Support\Exceptions;

use Exception;
use Throwable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GraphApiException extends Exception
{
    /**
     * The error type associated with the exception.
     *
     * @var string
     */
    protected $errorType;

    /**
     * The error subcode associated with the exception.
     *
     * @var mixed
     */
    protected $errorSubcode;

    /**
     * Create a new GraphApiException instance.
     *
     * @param string|null $message The exception message.
     * @param mixed|null $code The error code or subcode.
     * @param int $statusCode The HTTP status code.
     * @param Throwable|null $previous The previous exception if available.
     */
    public function __construct($message = null, $code = null, $statusCode = Response::HTTP_BAD_REQUEST, Throwable $previous = null)
    {
        $this->errorSubcode = $code;
        $this->errorType = basename(str_replace('\\', '/', get_class($this)));

        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Report or log the exception.
     *
     * You can log or report the exception here if needed.
     */
    public function report()
    {
        // Log or report the exception if necessary
    }

    /**
     * Render the exception into a JSON response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse The JSON response with formatted error.
     */
    public function render($request): JsonResponse
    {
        return new JsonResponse(
            data: $this->getFormattedError(),
            status: $this->getCode()
        );
    }

    /**
     * Get the formatted error response.
     *
     * @return array The formatted error response array.
     */
    protected function getFormattedError(): array
    {
        return [
            'error' => [
                'message'  => $this->getMessage(),
                'type'     => $this->errorType,
                'code'     => $this->errorSubcode ?? 'fatal_error',
                'trace_id' => $this->generateID(),
            ],
        ];
    }

    /**
     * Generate a random trace ID.
     *
     * @return string The generated trace ID.
     */
    public function generateID(): string
    {
        return substr(str_shuffle(
            "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"
        ), 0, 16);
    }
}
