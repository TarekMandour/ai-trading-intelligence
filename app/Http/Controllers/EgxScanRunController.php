<?php

namespace App\Http\Controllers;

use App\Models\EgxScanRun;
use Illuminate\View\View;

class EgxScanRunController extends Controller
{
    public function index(): View
    {
        // Do not hydrate the potentially very large JSON results column on the history page.
        $runs = EgxScanRun::query()
            ->select([
                'id',
                'egx_watchlist_id',
                'status',
                'ai_status',
                'profiles',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
            ])
            ->with('watchlist:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('egx.scans.index', compact('runs'));
    }

    public function show(EgxScanRun $scanRun): View
    {
        $scanRun->load('watchlist');

        return view('egx.scans.show', compact('scanRun'));
    }
}
