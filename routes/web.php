<?php

use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/user/tokens', function () {
        return view('user.tokens');
    })->name('user.tokens');

    // Quick ingest form submission (non-JavaScript fallback)
    Route::post('/dashboard/ingest', [IngestController::class, 'quickIngest'])
        ->name('dashboard.ingest');

    // RAG Quality Dashboard (admin only)
    Route::get('/admin/rag-dashboard', \App\Livewire\RagDashboard::class)
        ->name('admin.rag-dashboard')
        ->middleware('can:admin');
});

require __DIR__.'/settings.php';
