<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureRole
{
    /**
     * Allow only users whose role matches one of the given roles.
     *
     * Usage in routes:
     *   ->middleware('role:admin')
     *   ->middleware('role:user,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (!in_array($user->role, $roles, true)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
