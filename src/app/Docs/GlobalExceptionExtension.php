<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GlobalExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        // Domain exceptions and ValidationException should be excluded
        if ($type->isInstanceOf(\App\Exceptions\ServerErrorException::class) ||
            $type->isInstanceOf(\Illuminate\Validation\ValidationException::class)) {
            return false;
        }

        return $type->isInstanceOf(AuthenticationException::class) ||
               $type->isInstanceOf(AuthorizationException::class) ||
               $type->isInstanceOf(AccessDeniedHttpException::class) ||
               $type->isInstanceOf(ThrottleRequestsException::class) ||
               $type->isInstanceOf(NotFoundHttpException::class) ||
               $type->isInstanceOf(ModelNotFoundException::class) ||
               $type->isInstanceOf(BadRequestHttpException::class) ||
               $type->isInstanceOf(\Throwable::class);
    }

    public function toResponse(Type $type)
    {
        $className = ltrim($type->name, '\\');
        $baseName = class_basename($className);

        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $message = 'Server Error';
        $description = 'Unhandled server errors.';
        $exceptionTypeExample = $baseName;

        if ($type->isInstanceOf(AuthenticationException::class)) {
            $statusCode = 401;
            $errorCode = 'UNAUTHENTICATED';
            $message = 'You are not authenticated. Please provide a valid token.';
            $description = 'Requesting protected routes without a valid JWT token, or token is expired/invalid.';
        } elseif ($type->isInstanceOf(AccessDeniedHttpException::class)) {
            $statusCode = 403;
            $errorCode = 'FORBIDDEN';
            $message = 'You do not have permission to access this resource.';
            $description = 'Attempting to access a route or perform an action without the required user role or permissions.';
        } elseif ($type->isInstanceOf(AuthorizationException::class)) {
            $statusCode = 403;
            $errorCode = 'FORBIDDEN';
            $message = 'You do not have permission to perform this action.';
            $description = 'Attempting to access a route or perform an action without the required user role or permissions.';
        } elseif ($type->isInstanceOf(ThrottleRequestsException::class)) {
            $statusCode = 429;
            $errorCode = 'TOO_MANY_REQUESTS';
            $message = 'Too many requests. Please slow down and try again in a moment.';
            $description = 'Making too many requests in a short period of time, exceeding the rate limits.';
        } elseif ($type->isInstanceOf(NotFoundHttpException::class)) {
            $statusCode = 404;
            $errorCode = 'NOT_FOUND';
            $message = 'The requested endpoint does not exist.';
            $description = 'Requesting an endpoint or URL path that does not exist.';
        } elseif ($type->isInstanceOf(ModelNotFoundException::class)) {
            $statusCode = 404;
            $errorCode = 'NOT_FOUND';
            $message = 'The requested resource was not found.';
            $description = 'Requesting a database record or resource ID that does not exist.';
        } elseif ($type->isInstanceOf(BadRequestHttpException::class)) {
            $statusCode = 400;
            $errorCode = 'BAD_REQUEST';
            $message = 'The request is invalid or malformed.';
            $description = 'The request could not be understood or was malformed due to invalid syntax.';
        } else {
            // For general unhandled system exceptions
            $errorCode = 'SERVER_ERROR';
            $exceptionTypeExample = 'ServerErrorException';
            $message = 'Sorry, something went wrong on the server. Please try again later.';
            $description = 'An unexpected server error occurred during processing.';
        }

        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'error_code',
                (new OpenApiTypes\StringType)
                    ->setDescription('The global error code.')
                    ->example($errorCode)
            )
            ->addProperty(
                'exception_type',
                (new OpenApiTypes\StringType)
                    ->setDescription('The exception class name.')
                    ->example($exceptionTypeExample)
            )
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('A human-readable error message.')
                    ->example($message)
            )
            ->setRequired(['error_code', 'exception_type', 'message']);

        return Response::make($statusCode)
            ->setDescription($description)
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType),
            );
    }
}
