<?php

namespace App\Exceptions;

use Throwable;
use App\Support\Exceptions\OAuthException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Support\Exceptions\GraphApiException;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(
            reportUsing: fn(TokenInvalidException | TokenExpiredException | TokenBlacklistedException $e) => throw new OAuthException(code: 'token_could_not_verified')
        );

        $this->reportable(
            reportUsing: fn(JWTException $e) => throw new OAuthException(code: 'token_could_not_parse', statusCode: Response::HTTP_INTERNAL_SERVER_ERROR)
        );

        $this->reportable(
            reportUsing: fn(Throwable $e) => //dd($e)
            throw new GraphApiException(
                //check if app is in production
                message: config('app.env') !== 'production' ? $e->getMessage() : 'Sorry, There was something went wrong on our side, Please try again later.'
            )
        );
    }
}
