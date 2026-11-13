<?php

namespace App\Http\Controllers;

use App\Models\EgxScanRun;
use Illuminate\View\View;

class EgxScanRunController extends Controller
{
    public function index(): View
    {
        $runs = EgxScanRun::with('watchlist')->latest()->paginate(20);
        return view('egx.scans.index', compact('runs'));
    }

    public function show(EgxScanRun $scanRun): View
    {
        $scanRun->load('watchlist');
        return view('egx.scans.show', compact('scanRun'));
    }
}
