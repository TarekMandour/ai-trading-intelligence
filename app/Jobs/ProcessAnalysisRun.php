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

    public int $timeout = 300;

    public array $backoff = [10, 30];

    public function __construct(public int $analysisRunId) {}

    public function handle(BinanceSpotClient $binance, DeterministicAnalysisEngine $engine, ExecutionCostEstimator $costs, MultiTimeframeAlignment $alignment, ScenarioEvaluator $scenarios, AiAnalysisProvider $ai, AiReviewValidator $validator): void
    {
        $run = AnalysisRun::findOrFail($this->analysisRunId);
        if (in_array($run->status, ['COMPLETED', 'FAILED'], true)) {
            return;
        }

        $run->update(['status' => 'RUNNING', 'started_at' => $run->started_at ?? now()]);
        $warnings = [];
        try {
            $universe = $binance->eligibleUsdtUniverse();
        } catch (\Throwable $exception) {
            $run->update(['status' => 'FAILED', 'failure_reason' => 'BINANCE_UNAVAILABLE', 'completed_at' => now()]);
            throw $exception;
        }

        if ($universe['symbols'] === []) {
            $run->update(['status' => 'FAILED', 'failure_reason' => 'EMPTY_ELIGIBLE_UNIVERSE', 'completed_at' => now()]);
            return;
        }

        $profileCount = 0;
        $aiStatuses = [];
        foreach ($universe['symbols'] as $symbolData) {
            $symbol = $symbolData['symbol'];
            foreach (config('analysis.profiles') as $profile => $timeframes) {
                $states = [];
                $profileWarnings = [];
                try {
                    foreach ($timeframes as $role => $interval) {
                        $candles = $binance->candles($symbol, $interval);
                        if (count($candles) < 20) {
                            throw new \RuntimeException('INSUFFICIENT_CANDLE_HISTORY');
                        }
                        $states[$role] = $engine->evaluate($candles);
                    }
                } catch (\Throwable $exception) {
                    $profileWarnings[] = 'CANDLE_DATA_UNAVAILABLE';
                }

                $alignmentResult = $alignment->summarize(collect($states)->map(fn (array $state): array => ['direction' => $state['direction'], 'behavior' => $state['behavior']])->all());
                $entryState = $states['entry'] ?? null;
                $regime = $entryState ?? ['direction' => 'UNCERTAIN', 'behavior' => 'UNCERTAIN', 'volatility' => 'NORMAL', 'reasons' => ['INSUFFICIENT_VALIDATED_CANDLE_EVIDENCE'], 'candidate' => null];
                $scenarioResults = $scenarios->evaluate($regime);
                $candidate = $regime['candidate'] ?? null;
                $decision = 'NO_OPPORTUNITY';
                $executionCosts = ['available' => false, 'fee' => 0, 'spread' => 0, 'slippage' => 0, 'total' => 0];

                if ($candidate !== null && $alignmentResult['state'] === 'ALIGNED') {
                    try {
                        $book = $binance->orderBook($symbol);
                        $executionCosts = $costs->estimateFromOrderBook($book, (float) config('analysis.trade_notional'), (float) config('analysis.fee_rate'));
                        $decision = $executionCosts['available'] ? 'ENTERABLE' : 'WAIT';
                    } catch (\Throwable $exception) {
                        $profileWarnings[] = 'ORDER_BOOK_UNAVAILABLE';
                        $decision = 'WAIT';
                    }
                } elseif ($candidate !== null) {
                    $decision = 'WAIT';
                }

                $evidence = [
                    'symbol' => $symbol,
                    'profile' => $profile,
                    'evidence_ids' => ['market_regime', 'timeframe_alignment', 'scenarios', 'execution_costs', 'decision'],
                    'timeframe_states' => $states,
                    'timeframe_alignment' => $alignmentResult,
                    'regime' => array_diff_key($regime, ['candidate' => true, 'reasons' => true]),
                    'scenarios' => $scenarioResults,
                    'execution_costs' => $executionCosts,
                    'decision' => $decision,
                    'decision_reasons' => array_values(array_unique(array_merge($regime['reasons'] ?? [], $alignmentResult['reasons'] ?? [], $profileWarnings))),
                    'candidate' => $candidate,
                ];
                $aiReview = ($decision === 'ENTERABLE' && $candidate !== null && $alignmentResult['state'] === 'ALIGNED')
                    ? $this->reviewAi($ai, $validator, $evidence)
                    : ['status' => config('services.openai.key') ? 'NOT_REQUIRED' : 'NOT_CONFIGURED'];
                $aiStatuses[] = $aiReview['status'];

                DB::transaction(function () use ($run, $profile, $symbol, $decision, $timeframes, $evidence, $executionCosts, $aiReview, $profileWarnings, $candidate): void {
                    $report = $run->reports()->firstOrCreate(
                        ['profile' => $profile, 'symbol' => $symbol],
                        ['status' => $decision, 'timeframes' => $timeframes + ['states' => $evidence['timeframe_states'], 'alignment' => $evidence['timeframe_alignment'], 'regime' => $evidence['regime'], 'scenarios' => $evidence['scenarios'], 'execution_costs' => $executionCosts], 'reasons' => $evidence['decision_reasons'], 'data_quality_warnings' => $profileWarnings, 'ai_review' => $aiReview]
                    );
                    $opportunity = $report->opportunity()->firstOrCreate(
                        [],
                        ['symbol' => $symbol, 'scenario' => $candidate ? 'TREND_PULLBACK_CONTINUATION' : null, 'decision' => $decision, 'entry' => $candidate['entry'] ?? null, 'stop' => $candidate['stop'] ?? null, 'target_1' => $candidate['target1'] ?? null, 'target_2' => $candidate['target2'] ?? null, 'snapshot' => $evidence]
                    );
                    foreach (['market_regime', 'timeframe_alignment', 'scenarios', 'execution_costs', 'decision'] as $key) {
                        $opportunity->evidence()->firstOrCreate(
                            ['evidence_key' => $key],
                            ['kind' => strtoupper($key), 'payload' => $evidence[$key] ?? $evidence['regime'], 'recorded_at' => now()]
                        );
                    }
                    $opportunity->aiReview()->firstOrCreate(
                        [],
                        ['provider' => config('services.openai.key') ? 'openai' : null, 'model' => config('services.openai.model'), 'status' => $aiReview['status'], 'review' => $aiReview['review'] ?? null]
                    );
                    AnalysisAuditEvent::firstOrCreate(
                        ['analysis_run_id' => $run->id, 'fingerprint' => hash('sha256', $report->id.'|DECISION_RECORDED|'.$decision)],
                        ['analysis_report_id' => $report->id, 'analysis_opportunity_id' => $opportunity->id, 'event' => 'DECISION_RECORDED', 'payload' => ['symbol' => $symbol, 'profile' => $profile, 'decision' => $decision], 'occurred_at' => now()]
                    );
                });
                $profileCount++;
            }
        }

        $run->update([
            'status' => 'COMPLETED',
            'ai_status' => ! config('services.openai.key') ? 'NOT_CONFIGURED' : (in_array('FAILED', $aiStatuses, true) ? 'PARTIAL_FAILURE' : (in_array('REJECTED', $aiStatuses, true) ? 'PARTIAL_REJECTED' : 'COMPLETED')),
            'completed_at' => now(),
            'audit' => ['universe_count' => count($universe['symbols']), 'reports_created' => $profileCount, 'source' => 'binance_spot_rest', 'warnings' => array_values(array_unique($warnings))],
        ]);
    }

    private function reviewAi(AiAnalysisProvider $ai, AiReviewValidator $validator, array $evidence): array
    {
        if (! config('services.openai.key')) {
            return ['status' => 'NOT_CONFIGURED'];
        }
        try {
            $review = $ai->review($evidence);
        } catch (\Throwable $exception) {
            return ['status' => 'FAILED'];
        }

        return $validator->validate($review, $evidence['evidence_ids'])
            ? ['status' => 'COMPLETED', 'review' => $review]
            : ['status' => 'REJECTED'];
    }
}
