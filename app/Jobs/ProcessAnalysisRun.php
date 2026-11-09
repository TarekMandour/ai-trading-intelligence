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
            $run->update(['status' => 'RUNNING', 'failure_reason' => 'BINANCE_UNAVAILABLE']);
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
            $candlesByInterval = [];
            foreach (config('analysis.profiles') as $profile => $timeframes) {
                $states = [];
                $entryCandles = [];
                $profileWarnings = [];
                try {
                    foreach ($timeframes as $role => $interval) {
                        if (! array_key_exists($interval, $candlesByInterval)) {
                            try {
                                $candlesByInterval[$interval] = $binance->candles($symbol, $interval);
                            } catch (\Throwable $exception) {
                                $candlesByInterval[$interval] = null;
                            }
                        }
                        $candles = $candlesByInterval[$interval];
                        if (! is_array($candles) || count($candles) < 20) {
                            throw new \RuntimeException('INSUFFICIENT_CANDLE_HISTORY');
                        }
                        $states[$role] = $engine->evaluate($candles);
                        if ($role === 'entry') {
                            $entryCandles = $candles;
                        }
                    }
                } catch (\Throwable $exception) {
                    $profileWarnings[] = 'CANDLE_DATA_UNAVAILABLE';
                }

                $alignmentResult = $alignment->summarize(collect($states)->map(fn (array $state): array => ['direction' => $state['direction'], 'behavior' => $state['behavior']])->all());
                $entryState = $states['entry'] ?? null;
                $regime = $entryState ?? ['direction' => 'UNCERTAIN', 'behavior' => 'UNCERTAIN', 'volatility' => 'NORMAL', 'reasons' => ['INSUFFICIENT_VALIDATED_CANDLE_EVIDENCE'], 'candidate' => null];
                $scenarioResults = $scenarios->evaluate($regime, $entryCandles);
                $selectedScenario = collect($scenarioResults)->first(fn (array $scenario): bool => $scenario['status'] === 'ENTERABLE' && is_array($scenario['candidate'] ?? null));
                $candidate = $selectedScenario['candidate'] ?? null;
                $hasUncertainTimeframe = collect($states)->contains(fn (array $state): bool => ($state['direction'] ?? 'UNCERTAIN') === 'UNCERTAIN');
                $decision = ($profileWarnings !== [] || count($states) !== count($timeframes) || $hasUncertainTimeframe) ? 'REJECTED' : 'NO_OPPORTUNITY';
                $executionCosts = ['available' => false, 'fee' => 0, 'spread' => 0, 'slippage' => 0, 'total' => 0];

                // A candidate is not an entry signal by itself. The selected scenario must
                // explicitly confirm ENTERABLE before execution-cost gating can pass.
                if ($candidate !== null && $alignmentResult['state'] === 'ALIGNED') {
                    try {
                        $book = $binance->orderBook($symbol);
                        $executionCosts = $costs->estimateFromOrderBook($book, (float) config('analysis.trade_notional'), (float) config('analysis.fee_rate'));
                        $baseQuantity = (float) ($executionCosts['base_quantity'] ?? 0);
                        $risk = (float) $candidate['entry'] - (float) $candidate['stop'];
                        $reward = (float) $candidate['target1'] - (float) $candidate['entry'];
                        $costPerUnit = $baseQuantity > 0 ? (float) $executionCosts['total'] / $baseQuantity : INF;
                        $netRiskReward = ($risk + $costPerUnit) > 0 ? ($reward - $costPerUnit) / ($risk + $costPerUnit) : 0.0;
                        $executionCosts['net_risk_reward'] = $netRiskReward;
                        $decision = $executionCosts['available'] && $netRiskReward >= 1.5 ? 'ENTERABLE' : 'WAIT';
                    } catch (\Throwable $exception) {
                        $profileWarnings[] = 'ORDER_BOOK_UNAVAILABLE';
                        $decision = 'WAIT';
                    }
                } elseif ($candidate !== null && $decision !== 'REJECTED') {
                    $decision = 'WAIT';
                }
                $warnings = array_merge($warnings, array_map(fn (string $warning): string => $symbol.':'.$profile.':'.$warning, $profileWarnings));

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
                    'decision_reasons' => array_values(array_unique(array_merge(
                        $regime['reasons'] ?? [],
                        $alignmentResult['reasons'] ?? [],
                        collect($scenarioResults)->flatMap(fn (array $scenario): array => $scenario['reasons'] ?? [])->all(),
                        isset($executionCosts['reason']) ? [$executionCosts['reason']] : [],
                        $decision === 'WAIT' ? ['ENTRY_GATES_NOT_FULLY_SATISFIED'] : [],
                        $profileWarnings,
                    ))),
                    'candidate' => $candidate,
                ];
                $aiReview = ($decision === 'ENTERABLE' && $candidate !== null && $alignmentResult['state'] === 'ALIGNED')
                    ? $this->reviewAi($ai, $validator, $evidence)
                    : ['status' => config('services.openai.key') ? 'NOT_REQUIRED' : 'NOT_CONFIGURED'];
                $aiStatuses[] = $aiReview['status'];

                DB::transaction(function () use ($run, $profile, $symbol, $decision, $timeframes, $evidence, $executionCosts, $aiReview, $profileWarnings, $candidate, $selectedScenario): void {
                    $report = $run->reports()->firstOrCreate(
                        ['profile' => $profile, 'symbol' => $symbol],
                        ['status' => $decision, 'timeframes' => $timeframes + ['states' => $evidence['timeframe_states'], 'alignment' => $evidence['timeframe_alignment'], 'regime' => $evidence['regime'], 'scenarios' => $evidence['scenarios'], 'execution_costs' => $executionCosts], 'reasons' => $evidence['decision_reasons'], 'data_quality_warnings' => $profileWarnings, 'ai_review' => $aiReview]
                    );
                    $opportunity = $report->opportunity()->firstOrCreate(
                        [],
                        ['symbol' => $symbol, 'scenario' => $candidate ? ($selectedScenario['scenario'] ?? null) : null, 'decision' => $decision, 'entry' => $candidate['entry'] ?? null, 'stop' => $candidate['stop'] ?? null, 'target_1' => $candidate['target1'] ?? null, 'target_2' => $candidate['target2'] ?? null, 'snapshot' => $evidence]
                    );
                    foreach (['market_regime', 'timeframe_alignment', 'scenarios', 'execution_costs', 'decision'] as $key) {
                        $opportunity->evidence()->firstOrCreate(
                            ['evidence_key' => $key],
                            ['kind' => strtoupper($key), 'payload' => $evidence[$key] ?? $evidence['regime'], 'recorded_at' => now()]
                        );
                    }
                    $opportunity->aiReview()->firstOrCreate(
                        [],
                        ['provider' => $aiReview['metadata']['provider'] ?? (config('services.openai.key') ? 'openai' : null), 'model' => $aiReview['metadata']['model'] ?? config('services.openai.model'), 'status' => $aiReview['status'], 'latency_ms' => $aiReview['latency_ms'] ?? null, 'usage' => $aiReview['metadata']['usage'] ?? null, 'review' => $aiReview['review'] ?? null]
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
            'ai_status' => ! config('services.openai.key') ? 'NOT_CONFIGURED' : (in_array('FAILED', $aiStatuses, true) ? 'PARTIAL_FAILURE' : (in_array('REJECTED', $aiStatuses, true) ? 'PARTIAL_REJECTED' : (in_array('COMPLETED', $aiStatuses, true) ? 'COMPLETED' : 'NOT_REQUIRED'))),
            'completed_at' => now(),
            'audit' => ['universe_count' => count($universe['symbols']), 'reports_created' => $profileCount, 'source' => 'binance_spot_rest', 'warnings' => array_values(array_unique($warnings))],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        AnalysisRun::whereKey($this->analysisRunId)->update([
            'status' => 'FAILED',
            'failure_reason' => 'JOB_FAILED',
            'completed_at' => now(),
        ]);
    }

    private function reviewAi(AiAnalysisProvider $ai, AiReviewValidator $validator, array $evidence): array
    {
        if (! config('services.openai.key')) {
            return ['status' => 'NOT_CONFIGURED'];
        }

        $startedAt = microtime(true);
        try {
            $result = $ai->review($evidence);
            $review = $result['review'] ?? $result;
            $metadata = $result['metadata'] ?? [];
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        } catch (\Throwable $exception) {
            return ['status' => 'FAILED', 'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000)];
        }

        $metadata['provider'] = 'openai';

        return $validator->validate($review, $evidence['evidence_ids'])
            ? ['status' => 'COMPLETED', 'review' => $review, 'metadata' => $metadata, 'latency_ms' => $latencyMs]
            : ['status' => 'REJECTED', 'metadata' => $metadata, 'latency_ms' => $latencyMs];
    }
}
