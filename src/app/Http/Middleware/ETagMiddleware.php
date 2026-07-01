<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Cache;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ETagMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip streamed and binary file responses to avoid buffering files into memory
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        // Skip if the response explicitly states not to store/cache the content
        $cacheControl = $response->headers->get('Cache-Control');
        if ($cacheControl && str_contains($cacheControl, 'no-store')) {
            return $response;
        }

        // Ensure the response status is successful (e.g. 200 OK)
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return $response;
        }

        // Skip if the response body content is empty
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        // Append X-Cache-Status header dynamically for telemetry
        $response->headers->set('X-Cache-Status', Cache::status());

        // Enforce no-store caching rules for write requests using Symfony's native cache configuration
        if (!$response->headers->has('Cache-Control')) {
            $response->setCache([
                'no_store'        => true,
                'must_revalidate' => true,
                'max_age'         => 0,
            ]);
        }

        // For write requests skip ETag generation
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $response;
        }

        // Generate ETag if not present
        if (!$etag = $response->headers->get('ETag')) {
            $etag = md5($response->getContent() ?: '*');
        }

        // Configure ETag and Cache-Control headers using Symfony's built-in setCache method
        $response->setCache([
            'etag'            => $etag,
            'private'         => true,
            'no_cache'        => true,
            'must_revalidate' => true,
        ]);

        // Set Vary headers using Symfony's native method to prevent cache poisoning
        $response->setVary('Accept, Authorization');

        // Check if response matches client-side caches and return 304 if valid
        if ($response->isNotModified($request)) {
            $response->setNotModified();
        }

        return $response;
    }
}
