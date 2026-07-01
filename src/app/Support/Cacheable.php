<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \App\Support\CacheBuilder
 */
trait Cacheable
{
    /**
     * Override builder instantiation to return our CacheBuilder.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CacheBuilder($query);
    }

    /**
     * Automatically hook model events to invalidate cache.
     * Runs during model boot lifecycle.
     */
    public static function bootCacheable(): void
    {
        static::saved(fn (Model $model) => $model->invalidateCache());
        static::deleted(fn (Model $model) => $model->invalidateCache());
        static::updated(fn (Model $model) => $model->invalidateCache());
        static::created(fn (Model $model) => $model->invalidateCache());
    }

    /**
     * Invalidate all cached queries associated with this model class by flushing the class tag.
     */
    public function invalidateCache(): void
    {
        Cache::flush(static::class);
    }
}
