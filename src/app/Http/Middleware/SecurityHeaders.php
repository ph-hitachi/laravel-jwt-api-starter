<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Append security headers to every response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent browsers from misinterpreting the content type (MIME-sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking by blocking the API from being embedded in an iframe
        $response->headers->set('X-Frame-Options', 'DENY');

        // Enable strict cross-site scripting (XSS) filtering
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Ensure no referrer information is leaked to third parties
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Advanced Content Security Policy for API (block all active content, framing, and explicitly prevent SVG/XSS script execution)
        $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none';");

        // Remove X-Powered-By to prevent technology fingerprinting
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
