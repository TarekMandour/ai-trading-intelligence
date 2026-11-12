<?php

use App\Http\Controllers\EgxMarketController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/thndr/capture/status', [EgxMarketController::class, 'captureStatus']);
    Route::post('/thndr/capture/start', [EgxMarketController::class, 'captureStart']);
    Route::post('/thndr/capture/stop', [EgxMarketController::class, 'captureStop']);
    Route::post('/thndr/token', [EgxMarketController::class, 'captureToken'])->middleware('throttle:6,1');

    Route::post('/egx/universe/sync', [EgxMarketController::class, 'syncUniverse']);
    Route::get('/egx/watchlists', [EgxMarketController::class, 'watchlists']);
    Route::post('/egx/watchlists', [EgxMarketController::class, 'createWatchlist']);
    Route::put('/egx/watchlists/{watchlist}', [EgxMarketController::class, 'updateWatchlist']);
    Route::get('/egx/watchlists/{watchlist}/scans', [EgxMarketController::class, 'scanHistory']);
    Route::post('/egx/scan', [EgxMarketController::class, 'scan']);
});
