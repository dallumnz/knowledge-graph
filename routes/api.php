<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FeedbackController;
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
    Route::get('/search/hybrid', [SearchController::class, 'hybrid'])->name('api.search.hybrid');
    Route::get('/search/text', [SearchController::class, 'textSearch'])->name('api.search.text');

    // RAG query endpoint with validation
    Route::post('/rag/query', [SearchController::class, 'ragQuery'])->name('api.rag.query');

    // User feedback endpoint
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('api.feedback.store');
    Route::get('/feedback/{queryId}', [FeedbackController::class, 'show'])->name('api.feedback.show');

    // Node endpoints
    Route::get('/nodes/{id}', [SearchController::class, 'show'])->name('api.nodes.show');

    // Document endpoints
    Route::get('/documents', [DocumentController::class, 'index'])->name('api.documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('api.documents.store');
    Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('api.documents.show');
    Route::put('/documents/{id}', [DocumentController::class, 'update'])->name('api.documents.update');
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('api.documents.destroy');
    Route::get('/documents/{id}/chunks', [DocumentController::class, 'chunks'])->name('api.documents.chunks');
});
