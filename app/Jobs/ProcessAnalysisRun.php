<?php

namespace App\Jobs;

use App\Models\AnalysisAuditEvent;
use App\Models\AnalysisRun;
use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\DeterministicAnalysisEngine;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\MultiTimeframeAlignment;
use App\Services\Analysis\ScenarioEvaluator;
use App\Services\MarketData\BinanceSpotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessAnalysisRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public array $backoff = [5];

    public function __construct(public int $analysisRunId) {}

    public function handle(BinanceSpotClient $binance, DeterministicAnalysisEngine $engine, ExecutionCostEstimator $costs, MultiTimeframeAlignment $alignment, ScenarioEvaluator $scenarios, AiAnalysisProvider $ai, AiReviewValidator $validator): void
    {
        $run = AnalysisRun::findOrFail($this->analysisRunId);
        if ($run->status !== 'QUEUED') {
            return;
        }
        $run->update(['status' => 'RUNNING', 'started_at' => now()]);
        $warnings = [];
        try {
            $universe = $binance->eligibleUsdtUniverse();
        } catch (\Throwable $exception) {
            $universe = ['symbols' => [], 'tickers' => []];
            $warnings[] = 'BINANCE_UNAVAILABLE';
        }
        foreach (config('analysis.profiles') as $profile => $timeframes) {
            $result = null;
            $states = [];
            if ($universe['symbols'] !== []) {
                try {
                    foreach ($timeframes as $role => $interval) { $states[$role] = $engine->evaluate($binance->candles($universe['symbols'][0]['symbol'], $interval)); }
                    $result = $states['entry'];
                } catch (\Throwable) {
                    $warnings[] = 'CANDLE_DATA_UNAVAILABLE';
                }
            }
            $alignmentResult = $alignment->summarize(collect($states)->map(fn (array $state): array => ['direction' => $state['direction'], 'behavior' => $state['behavior']])->all());
            $scenarioResults = $scenarios->evaluate($result ?? ['direction' => 'UNCERTAIN', 'behavior' => 'UNCERTAIN', 'volatility' => 'NORMAL']);
            $decision = $result !== null && $result['candidate'] !== null ? 'ENTERABLE' : 'NO_OPPORTUNITY';
            $executionCosts = ['available' => false];
            if ($decision === 'ENTERABLE') {
                try {
                    $executionCosts = $costs->estimateFromOrderBook($binance->orderBook($universe['symbols'][0]['symbol']), config('analysis.trade_notional'), config('analysis.fee_rate'));
                    if (! $executionCosts['available']) {
                        $decision = 'WAIT';
                    }
                } catch (\Throwable) {
                    $warnings[] = 'ORDER_BOOK_UNAVAILABLE';
                    $decision = 'WAIT';
                }
            }
            $evidence = ['candidate_id' => $profile, 'evidence_ids' => ['regime', 'decision', 'alignment', 'scenarios'], 'scenario' => $result['reasons'][0] ?? null, 'timeframe_alignment' => $alignmentResult['state'], 'regime' => $result ? array_diff_key($result, ['candidate' => true, 'reasons' => true]) : [], 'alignment' => $alignmentResult, 'scenarios' => $scenarioResults, 'decision' => $decision];
            $aiReview = $this->reviewAi($ai, $validator, $evidence);
            DB::transaction(function () use ($run, $profile, $decision, $timeframes, $evidence, $executionCosts, $result, $warnings, $aiReview): void {
                $report = $run->reports()->firstOrCreate(['profile' => $profile], ['status' => $decision, 'timeframes' => $timeframes + ['regime' => $evidence['regime'], 'execution_costs' => $executionCosts], 'reasons' => $result['reasons'] ?? ['INSUFFICIENT_VALIDATED_CANDLE_EVIDENCE'], 'data_quality_warnings' => array_unique($warnings), 'ai_review' => $aiReview]);
                $opportunity = $report->opportunity()->firstOrCreate([], ['scenario' => $evidence['scenario'], 'decision' => $decision, 'snapshot' => $evidence]);
                $opportunity->evidence()->firstOrCreate(['evidence_key' => 'decision'], ['kind' => 'DETERMINISTIC', 'payload' => $evidence, 'recorded_at' => now()]);
                $opportunity->aiReview()->firstOrCreate([], ['provider' => config('services.openai.key') ? 'openai' : null, 'model' => config('services.openai.model'), 'status' => $aiReview['status'], 'review' => $aiReview['review'] ?? null]);
                AnalysisAuditEvent::firstOrCreate(['analysis_run_id' => $run->id, 'fingerprint' => hash('sha256', $report->id.'|DECISION_RECORDED|'.$decision)], ['analysis_report_id' => $report->id, 'analysis_opportunity_id' => $opportunity->id, 'event' => 'DECISION_RECORDED', 'payload' => ['decision' => $decision], 'occurred_at' => now()]);
            });
        }
        $run->update(['status' => 'COMPLETED', 'completed_at' => now(), 'audit' => ['universe_count' => count($universe['symbols']), 'source' => 'binance_spot_rest']]);
    }

    private function reviewAi(AiAnalysisProvider $ai, AiReviewValidator $validator, array $evidence): array
    {
        if (! config('services.openai.key')) {
            return ['status' => 'NOT_CONFIGURED'];
        }
        try {
            $review = $ai->review($evidence);
        } catch (\Throwable) {
            return ['status' => 'FAILED'];
        }

        return $validator->validate($review, $evidence['evidence_ids']) ? ['status' => 'COMPLETED', 'review' => $review] : ['status' => 'REJECTED'];
    }
}
