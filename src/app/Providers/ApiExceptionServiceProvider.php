<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Import Exceptions
use App\Exceptions\ServerErrorException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;

class ApiExceptionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application exception renderers.
     */
    public function boot(ExceptionHandlerContract $handler): void
    {
        if (! $handler instanceof ExceptionHandler) {
            return;
        }

        // Force JSON rendering for API requests
        $handler->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        // Domain-specific exception handling
        $handler->renderable(function (ServerErrorException $e, Request $request) {
            return response()->json([
                'error_code'     => $e->getErrorCode(),
                'exception_type' => class_basename($e),
                'message'        => $e->getMessage(),
            ], $e->getStatusCode());
        });

        // 401 Unauthenticated
        $handler->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') && $request->bearerToken()) {
                try {
                    \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
                } catch (TokenBlacklistedException $e) {
                    return response()->json([
                        'error_code'     => 'TOKEN_BLACKLISTED',
                        'exception_type' => class_basename($e),
                        'message'        => 'The token has been blacklisted.',
                    ], 401);
                } catch (TokenExpiredException $e) {
                    return response()->json([
                        'error_code'     => 'TOKEN_EXPIRED',
                        'exception_type' => class_basename($e),
                        'message'        => 'The token has expired.',
                    ], 401);
                } catch (TokenInvalidException $e) {
                    return response()->json([
                        'error_code'     => 'TOKEN_INVALID',
                        'exception_type' => class_basename($e),
                        'message'        => 'The token is invalid.',
                    ], 401);
                } catch (JWTException $e) {
                    return response()->json([
                        'error_code'     => 'TOKEN_COULD_NOT_PARSE',
                        'exception_type' => class_basename($e),
                        'message'        => 'The token could not be parsed.',
                    ], 400);
                }
            }

            return response()->json([
                'error_code'     => 'UNAUTHENTICATED',
                'exception_type' => class_basename($e),
                'message'        => 'You are not authenticated. Please provide a valid token.',
            ], Response::HTTP_UNAUTHORIZED);
        });

        // 403 Forbidden (Gate / Policy)
        $handler->renderable(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'error_code'     => 'FORBIDDEN',
                'exception_type' => class_basename($e),
                'message'        => 'You do not have permission to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        });

        // 403 Forbidden (Access Denied)
        $handler->renderable(function (AccessDeniedHttpException $e, Request $request) {
            $isAuthz = $e->getPrevious() instanceof AuthorizationException;
            return response()->json([
                'error_code'     => 'FORBIDDEN',
                'exception_type' => class_basename($e),
                'message'        => $isAuthz 
                    ? 'You do not have permission to perform this action.' 
                    : 'You do not have permission to access this resource.',
            ], Response::HTTP_FORBIDDEN);
        });

        // 404 Not Found
        $handler->renderable(function (NotFoundHttpException $e, Request $request) {
            $previous = $e->getPrevious();
            $message  = $previous instanceof ModelNotFoundException
                ? 'The requested resource was not found.'
                : 'The requested endpoint does not exist.';

            return response()->json([
                'error_code'     => 'NOT_FOUND',
                'exception_type' => $previous ? class_basename($previous) : class_basename($e),
                'message'        => $message,
            ], Response::HTTP_NOT_FOUND);
        });

        // 405 Method Not Allowed
        $handler->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'error_code'     => 'METHOD_NOT_ALLOWED',
                'exception_type' => class_basename($e),
                'message'        => 'Invalid HTTP method.',
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        });

        // 400 Bad Request
        $handler->renderable(function (BadRequestHttpException $e, Request $request) {
            return response()->json([
                'error_code'     => 'BAD_REQUEST',
                'exception_type' => class_basename($e),
                'message'        => $e->getMessage() ?: 'The request is invalid or malformed.',
            ], Response::HTTP_BAD_REQUEST);
        });

        // 422 Validation Error
        $handler->renderable(function (ValidationException $e, Request $request) {
            return response()->json([
                'error_code'     => 'VALIDATION_ERROR',
                'exception_type' => class_basename($e),
                'message'        => 'The given data was invalid.',
                'errors'         => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // 429 Too Many Requests
        $handler->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            return response()->json([
                'error_code'     => 'TOO_MANY_REQUESTS',
                'exception_type' => class_basename($e),
                'message'        => 'Too many requests. Please slow down and try again in a moment.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        });

        // JWT Exceptions
        $handler->renderable(function (TokenInvalidException $e, Request $request) {
            return response()->json([
                'error_code'     => 'TOKEN_INVALID',
                'exception_type' => class_basename($e),
                'message'        => 'The token is invalid.',
            ], 401);
        });

        $handler->renderable(function (TokenExpiredException $e, Request $request) {
            return response()->json([
                'error_code'     => 'TOKEN_EXPIRED',
                'exception_type' => class_basename($e),
                'message'        => 'The token has expired.',
            ], 401);
        });

        $handler->renderable(function (TokenBlacklistedException $e, Request $request) {
            return response()->json([
                'error_code'     => 'TOKEN_BLACKLISTED',
                'exception_type' => class_basename($e),
                'message'        => 'The token has been blacklisted.',
            ], 401);
        });

        $handler->renderable(function (JWTException $e, Request $request) {
            return response()->json([
                'error_code'     => 'TOKEN_COULD_NOT_PARSE',
                'exception_type' => class_basename($e),
                'message'        => 'The token could not be parsed.',
            ], 400);
        });

        // 500 Fallback (Uncaught Exception)
        $handler->renderable(function (\Throwable $e, Request $request) {
            if ($e instanceof ServerErrorException ||
                $e instanceof AuthenticationException ||
                $e instanceof AuthorizationException ||
                $e instanceof AccessDeniedHttpException ||
                $e instanceof ValidationException ||
                $e instanceof NotFoundHttpException ||
                $e instanceof MethodNotAllowedHttpException ||
                $e instanceof TooManyRequestsHttpException ||
                $e instanceof BadRequestHttpException ||
                $e instanceof JWTException) {
                return null;
            }

            if ($request->is('api/*')) {
                $unexpected = new ServerErrorException('Sorry, something went wrong on the server. Please try again later.');
                return response()->json([
                    'error_code'     => $unexpected->getErrorCode(),
                    'exception_type' => class_basename($unexpected),
                    'message'        => $unexpected->getMessage(),
                ], $unexpected->getStatusCode());
            }
        });
    }
}
