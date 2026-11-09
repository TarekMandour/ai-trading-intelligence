<?php

use App\Http\Controllers\AnalysisRunController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/analysis-runs');
Route::resource('analysis-runs', AnalysisRunController::class)->only(['index', 'store', 'show']);
