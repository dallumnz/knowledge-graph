<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cacheable Component Trait
 *
 * Provides caching functionality for Livewire components.
 * Use this trait to cache expensive computations or partial renders.
 *
 * Usage:
 *   use CacheableComponent;
 *
 *   protected function getCachedData(string $key, callable $callback, int $ttl = 300): mixed
 *   {
 *       return Cache::remember($key, $ttl, $callback);
 *   }
 */
trait CacheableComponent
{
    /**
     * Get a cache key for this component.
     *
     * @param string $suffix
     * @return string
     */
    protected function componentCacheKey(string $suffix = ''): string
    {
        $class = str_replace('\\', '_', static::class);
        $id = $this->getId() ?? 'default';
        
        return "livewire:{$class}:{$id}:{$suffix}";
    }

    /**
     * Get cached data or compute and cache it.
     *
     * @param string $key Suffix for the cache key
     * @param callable $callback Function to compute the data
     * @param int $ttl Cache TTL in seconds
     * @return mixed
     */
    protected function getCachedData(string $key, callable $callback, int $ttl = 300): mixed
    {
        $cacheKey = $this->componentCacheKey($key);
        
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get the component's user ID for user-specific caching.
     *
     * @return int|null
     */
    protected function getCacheUserId(): ?int
    {
        return auth()->id();
    }

    /**
     * Generate a user-specific cache key.
     *
     * @param string $suffix
     * @return string
     */
    protected function userCacheKey(string $suffix = ''): string
    {
        $userId = $this->getCacheUserId() ?? 'guest';
        $class = class_basename(static::class);
        
        return "livewire:{$class}:user:{$userId}:{$suffix}";
    }

    /**
     * Clear the component's cache.
     *
     * @param string $key Suffix to clear (empty clears all)
     */
    protected function clearComponentCache(string $key = ''): void
    {
        $pattern = $key 
            ? $this->componentCacheKey($key)
            : str_replace(':default', ':*', $this->componentCacheKey(''));
        
        // For simple key-based caching, use forget
        if ($key) {
            Cache::forget($this->componentCacheKey($key));
        }
    }

    /**
     * Cache rendered HTML for the component.
     *
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @return string
     */
    protected function getCachedHtml(string $key, callable $callback, int $ttl = 300): string
    {
        $cacheKey = "livewire:html:" . static::class . ":{$key}";
        
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Invalidate all caches for this component type.
     *
     * @return void
     */
    public function invalidateAllComponentCaches(): void
    {
        $class = class_basename(static::class);
        Cache::tags(["livewire:{$class}"])->flush();
    }
}
