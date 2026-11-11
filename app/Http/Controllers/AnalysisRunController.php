<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAnalysisRun;
use App\Models\AnalysisRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnalysisRunController extends Controller
{
    public function index(): View
    {
        return view('analysis.index', ['runs' => AnalysisRun::with('reports')->latest()->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $aiProvider = (string) config('services.ai.provider', 'openai');
        $aiProviderKey = match ($aiProvider) {
            'openai' => config('services.openai.key'),
            'gemini' => config('services.gemini.key'),
            default => null,
        };

        $run = AnalysisRun::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'QUEUED',
            'configuration_version' => config('analysis.configuration_version'),
            'ai_status' => filled($aiProviderKey) ? 'PENDING' : 'NOT_CONFIGURED',
        ]);
        ProcessAnalysisRun::dispatch($run->id);

        return redirect()->route('analysis-runs.show', $run)->with('status', 'Analysis queued. Start the database queue worker to process it.');
    }

    public function show(AnalysisRun $analysisRun): View
    {
        $analysisRun->load(['reports.opportunity.evidence', 'reports.opportunity.aiReview']);

        return view('analysis.show', compact('analysisRun'));
    }
}
