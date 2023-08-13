<?php

namespace App\Http\Middleware\JWT;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class AuthenticateMiddleware extends BaseMiddleware
{   
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->authenticate($request);

        return $next($request);
    }

    public function authenticate($request)
    {
        try {
            $this->auth->parseToken()->authenticate();
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
