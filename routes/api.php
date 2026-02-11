<?php

use App\Http\Middleware\CacheApiResponses;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
| Rate Limiting: All API routes are throttled to 60 requests per minute
| per user (authenticated via Sanctum). Adjust in throttle middleware.
|
| API Caching:
| - Enable via CACHE_API_ENABLED=true in .env
| - Configure TTL via CACHE_API_TTL (seconds)
| - Cache applies to GET /api/search, /api/search/text, /api/nodes/{id}
|
*/

Route::middleware(['auth:sanctum', 'throttle:60,1', 'cache.api'])->group(function () {
    // Ingestion endpoints
    Route::post('/ingest', [IngestController::class, 'store'])->name('api.ingest.store');

    // Search endpoints
    Route::get('/search', [SearchController::class, 'search'])->name('api.search');
    Route::get('/search/text', [SearchController::class, 'textSearch'])->name('api.search.text');

    // Node endpoints
    Route::get('/nodes/{id}', [SearchController::class, 'show'])->name('api.nodes.show');
});
