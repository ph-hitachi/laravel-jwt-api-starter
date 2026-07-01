<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache as LaravelCache;

class Cache
{
    private static string $lastStatus = 'MISS';

    /**
     * Retrieve a tagged cache entry, or compute, store, and return it.
     * Tracks whether the result came from cache (HIT) or was freshly computed (MISS).
     *
     *   $shops = Cache::remember(tag: $tag, key: $key, ttl: 900, callback: fn() => ...);
     *   return response()->json($shops)->header('X-Cache-Status', Cache::status());
     */
    public static function remember(string|array $tag, string $key, int $ttl, callable $callback): mixed
    {
        $tags = (array) $tag;
        $hit = LaravelCache::tags($tags)->has($key);
        self::$lastStatus = $hit ? 'HIT' : 'MISS';

        return LaravelCache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Return HIT or MISS for the most recent remember() call.
     * Accepts an optional value for readable inline syntax — the argument is ignored.
     *
     *   Cache::status()        // 'HIT'
     *   Cache::status($shops)  // 'HIT' — reads naturally after remember()
     */
    public static function status(mixed $value = null): string
    {
        return self::$lastStatus;
    }

    /**
     * Store a value under a tagged cache key (write-through).
     */
    public static function put(string|array $tag, string $key, mixed $value, int $ttl): void
    {
        LaravelCache::tags((array) $tag)->put($key, $value, $ttl);
    }

    /**
     * Remove a specific entry from a tagged cache.
     */
    public static function forget(string|array $tag, string $key): void
    {
        LaravelCache::tags((array) $tag)->forget($key);
    }

    /**
     * Evict every key under a tag at once.
     */
    public static function flush(string|array $tag): void
    {
        LaravelCache::tags((array) $tag)->flush();
    }
}
