<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache API responses for faster repeat requests.
 *
 * Configuration:
 * - CACHE_API_ENABLED=true to enable
 * - CACHE_API_TTL=60 (seconds) for cache duration
 *
 * Endpoints:
 * - GET /api/search - caches based on query hash
 * - GET /api/search/text - caches based on query hash
 * - GET /api/nodes/{id} - caches based on node ID
 */
class CacheApiResponses
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if caching is disabled
        if (!config('api.cache.enabled', false)) {
            return $next($request);
        }

        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key based on request
        $cacheKey = $this->generateCacheKey($request);

        // Check cache
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Get response and cache it
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $ttl = config('api.cache.ttl', 60);
            Cache::put($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Generate a unique cache key for the request.
     *
     * @param Request $request
     * @return string
     */
    private function generateCacheKey(Request $request): string
    {
        $path = $request->path();
        $query = $request->query();

        // For search endpoints, hash the query for shorter keys
        if (str_contains($path, 'search')) {
            $hash = hash('sha256', json_encode($query));
            return "api:response:search:{$hash}";
        }

        // For node endpoints, use the ID
        if (str_contains($path, 'nodes')) {
            $id = $request->route('id') ?? 'list';
            return "api:response:nodes:{$id}";
        }

        // Default: use full path + query
        return "api:response:" . hash('sha256', $path . json_encode($query));
    }

    /**
     * Invalidate cache for a specific endpoint.
     *
     * @param string $pattern Pattern to invalidate (e.g., 'search:*', 'nodes:5')
     */
    public static function invalidate(string $pattern): void
    {
        // This is a simplified invalidation approach
        // For production, consider using Cache::tags() for better control
        Cache::flush();
    }
}
