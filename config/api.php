<?php

/**
 * API Configuration
 *
 * Settings for API behavior including caching, rate limiting, and pagination.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | API Caching
    |--------------------------------------------------------------------------
    |
    | Configuration for API response caching.
    |
    | Enable caching to reduce load on the application and database.
    | Cache is applied to GET requests on search and node endpoints.
    |
    | TTL (Time To Live):
    | - Search results: shorter TTL (30-60 seconds) as results change frequently
    | - Node details: longer TTL (1 hour) as data is more static
    |
    */

    'cache' => [
        'enabled' => (bool) env('CACHE_API_ENABLED', false),
        'ttl' => (int) env('CACHE_API_TTL', 60),
        'search_ttl' => (int) env('CACHE_API_SEARCH_TTL', 30),
        'node_ttl' => (int) env('CACHE_API_NODE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default rate limit for API requests.
    | Adjust based on your server capacity and use case.
    |
    */

    'rate_limit' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default pagination settings for list endpoints.
    |
    */

    'pagination' => [
        'default_limit' => 10,
        'max_limit' => 100,
    ],

];
