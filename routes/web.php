<?php

use App\Http\Controllers\AnalysisRunController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/analysis-runs');
Route::resource('analysis-runs', AnalysisRunController::class)->only(['index', 'store', 'show']);

Route::prefix('egx/watchlists')->name('egx.watchlists.')->controller(EgxWatchlistController::class)->group(function (): void {\n    Route::get('/', 'index')->name('index');\n    Route::get('/create', 'create')->name('create');\n    Route::post('/', 'store')->name('store');\n    Route::get('/{watchlist}', 'show')->whereNumber('watchlist')->name('show');\n    Route::get('/{watchlist}/edit', 'edit')->whereNumber('watchlist')->name('edit');\n    Route::put('/{watchlist}', 'update')->whereNumber('watchlist')->name('update');\n    Route::delete('/{watchlist}', 'destroy')->whereNumber('watchlist')->name('destroy');\n});\n