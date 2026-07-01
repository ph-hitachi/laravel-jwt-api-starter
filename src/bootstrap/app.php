<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ETagMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Handle CORS requests
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Apply Security Headers and ETag to API routes group
        $middleware->api(append: [
            SecurityHeaders::class,
            ETagMiddleware::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'role'   => EnsureRole::class,
            'active' => EnsureUserIsActive::class,
            'etag'   => ETagMiddleware::class,
        ]);

        // Apply rate limiting to the api middleware group
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception handling is registered via App\Providers\ApiExceptionServiceProvider
    })->create();
