<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountDeactivatedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Block deactivated users and revoke their token immediately.
     *
     * @throws AccountDeactivatedException
     */
    public function handle(Request $request, Closure $next): Response
    {

        /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        $user = $guard->user();

        if ($user && !$user->is_active) {
            // Invalidate token
            $guard->logout();

            throw new AccountDeactivatedException();
        }

        return $next($request);
    }
}
