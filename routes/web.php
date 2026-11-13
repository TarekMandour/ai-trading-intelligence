<?php

use App\Http\Controllers\AnalysisRunController;
use App\Http\Controllers\EgxWatchlistController;\nuse App\Http\Controllers\EgxScanRunController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/analysis-runs');
Route::resource('analysis-runs', AnalysisRunController::class)->only(['index', 'store', 'show']);\nRoute::get('/egx/scans', [EgxScanRunController::class, 'index'])->name('egx.scans.index');\nRoute::get('/egx/scans/{scanRun}', [EgxScanRunController::class, 'show'])->whereNumber('scanRun')->name('egx.scans.show');

Route::prefix('egx/watchlists')->name('egx.watchlists.')->controller(EgxWatchlistController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{watchlist}', 'show')->whereNumber('watchlist')->name('show');
    Route::get('/{watchlist}/edit', 'edit')->whereNumber('watchlist')->name('edit');
    Route::put('/{watchlist}', 'update')->whereNumber('watchlist')->name('update');
    Route::delete('/{watchlist}', 'destroy')->whereNumber('watchlist')->name('destroy');
});
