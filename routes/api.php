<?php

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
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Ingestion endpoints
    Route::post('/ingest', [IngestController::class, 'store'])->name('api.ingest.store');

    // Search endpoints
    Route::get('/search', [SearchController::class, 'search'])->name('api.search');
    Route::get('/search/text', [SearchController::class, 'textSearch'])->name('api.search.text');

    // Node endpoints
    Route::get('/nodes/{id}', [SearchController::class, 'show'])->name('api.nodes.show');
});
