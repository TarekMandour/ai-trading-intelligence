<?php

namespace App\Jobs;

use App\Models\AnalysisAuditEvent;
use App\Models\AnalysisRun;
use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\DeterministicAnalysisEngine;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\IctComprehensiveEngine;
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

    public function handle(BinanceSpotClient $binance, DeterministicAnalysisEngine $engine, ExecutionCostEstimator $costs, IctComprehensiveEngine $ict, AiAnalysisProvider $ai, AiReviewValidator $validator): void
    {
        $run = AnalysisRun::findOrFail($this->analysisRunId);
        if (in_array($run->status, ['COMPLETED', 'FAILED'], true)) {
            return;
        }

        $run->update(['status' => 'RUNNING', 'started_at' => $run->started_at ?? now()]);
        $warnings = [];
        $savedUniverse = $run->audit['universe_symbols'] ?? null;
        if (is_array($savedUniverse) && $savedUniverse !== []) {
            $universe = ['symbols' => $savedUniverse];
        } else {
            try {
                $universe = $binance->eligibleUsdtUniverse();
            } catch (\Throwable $exception) {
                $run->update(['status' => 'RUNNING', 'failure_reason' => 'BINANCE_UNAVAILABLE']);
                throw $exception;
            }
            $run->update(['audit' => ['universe_symbols' => $universe['symbols'], 'source' => 'binance_spot_rest']]);
        }

        if ($universe['symbols'] === []) {
            $run->update(['status' => 'FAILED', 'failure_reason' => 'EMPTY_ELIGIBLE_UNIVERSE', 'completed_at' => now()]);
            return;
        }

        $profileCount = 0;
        $aiStatuses = [];
        $aiReviewCount = 0;
        foreach ($universe['symbols'] as $symbolData) {
            $symbol = $symbolData['symbol'];
            $candlesByInterval = [];
            foreach (config('analysis.profiles') as $profile => $timeframes) {
                $existingReport = $run->reports()->where('profile', $profile)->where('symbol', $symbol)->first(['id', 'ai_review']);
                if ($existingReport !== null) {
                    $existingReview = $existingReport->ai_review ?? [];
                    $aiStatuses[] = is_array($existingReview) ? ($existingReview['status'] ?? 'NOT_REQUIRED') : 'NOT_REQUIRED';
                    $profileCount++;
                    continue;
                }

                $candlesByRole = [];
                $states = [];
                $profileWarnings = [];
                $candleErrors = [];
                try {
                    foreach ($timeframes as $role => $interval) {
                        if (! array_key_exists($interval, $candlesByInterval)) {
                            try {
                                $candlesByInterval[$interval] = $binance->candles($symbol, $interval);
                            } catch (\Throwable $exception) {
                                $candlesByInterval[$interval] = null;
                                $candleErrors[$interval] = class_basename($exception);
                            }
                        }
                        $candles = $candlesByInterval[$interval];
                        if (! is_array($candles) || count($candles) < 20) {
                            $reason = $candleErrors[$interval] ?? 'INSUFFICIENT_CANDLE_HISTORY';
                            throw new \RuntimeException($role.':'.$interval.':'.$reason);
                        }
                        $candlesByRole[$role] = $candles;
                        $states[$role] = $engine->evaluate($candles);
                    }
                } catch (\Throwable $exception) {
                    $profileWarnings[] = 'CANDLE_DATA_UNAVAILABLE:'.$exception->getMessage();
                }

                $ictResult = config('ict.enabled', true) ? $ict->evaluate($candlesByRole, $profile) : ['scenarios' => [], 'decision' => 'NO_OPPORTUNITY', 'reasons' => ['ICT_STRATEGY_DISABLED']];
                $alignmentResult = ['state' => $ictResult['context']['bias'] ?? 'INSUFFICIENT', 'allow_entry' => ($ictResult['context']['bias'] ?? null) === 'BULLISH' && ($ictResult['context']['structure_bias'] ?? null) !== 'BEARISH', 'reasons' => $ictResult['reasons'] ?? []];
                $entryState = $states['entry'] ?? null;
                $regime = $entryState ?? ['direction' => 'UNCERTAIN', 'behavior' => 'UNCERTAIN', 'volatility' => 'NORMAL', 'reasons' => ['INSUFFICIENT_VALIDATED_CANDLE_EVIDENCE'], 'candidate' => null];
                $scenarioResults = $ictResult['scenarios'] ?? [];
                $selectedScenario = collect($scenarioResults)->first(fn (array $scenario): bool => $scenario['status'] === 'ENTERABLE' && is_array($scenario['candidate'] ?? null));
                $selectedScenario ??= collect($scenarioResults)->first(fn (array $scenario): bool => $scenario['status'] === 'WAIT' && is_array($scenario['candidate'] ?? null));
                $candidate = $selectedScenario['candidate'] ?? null;
                $marketEntryReady = ($candidate['entry_plans']['market']['status'] ?? null) === 'PLANNED';
                $decision = ($profileWarnings !== [] || count($candlesByRole) !== count($timeframes)) ? 'REJECTED' : ($ictResult['decision'] ?? 'NO_OPPORTUNITY');
                $executionCosts = ['available' => false, 'fee' => 0, 'spread' => 0, 'slippage' => 0, 'total' => 0, 'reason' => 'NOT_REQUESTED_NO_ENTERABLE_CANDIDATE'];
                if ($candidate === null && $decision !== 'REJECTED' && collect($scenarioResults)->contains(fn (array $scenario): bool => $scenario['status'] === 'WAIT')) {
                    $decision = 'WAIT';
                }

                if ($candidate !== null && $marketEntryReady && ($alignmentResult['allow_entry'] ?? false)) {
                    try {
                        $book = $binance->orderBook($symbol);
                        $executionCosts = $costs->estimateFromOrderBook($book, (float) config('analysis.trade_notional'), (float) config('analysis.fee_rate'));
                        if (! ($executionCosts['available'] ?? false)) {
                            $profileWarnings[] = 'EXECUTION_COSTS_UNAVAILABLE:'.($executionCosts['reason'] ?? 'UNKNOWN');
                        }
                        $baseQuantity = (float) ($executionCosts['base_quantity'] ?? 0);
                        if ($executionCosts['available'] && $baseQuantity > 0) {
                            $netMetrics = $costs->netRiskReward($candidate, $executionCosts);
                            $executionCosts = array_merge($executionCosts, $netMetrics);
                        }
                        $decision = $executionCosts['available'] && $baseQuantity > 0
                            && ($executionCosts['net_target1_reward_per_unit'] ?? 0) > 0
                            && ($executionCosts['net_risk_reward'] ?? 0) >= (float) config('analysis.minimum_net_risk_reward', 1.5)
                            ? 'ENTERABLE'
                            : 'WAIT';
                    } catch (\Throwable $exception) {
                        $executionCosts['reason'] = 'ORDER_BOOK_REQUEST_FAILED';
                        $profileWarnings[] = 'ORDER_BOOK_UNAVAILABLE';
                        $decision = 'WAIT';
                    }
                } elseif ($candidate !== null && $decision !== 'REJECTED') {
                    $decision = 'WAIT';
                    if (! $marketEntryReady) $executionCosts['reason'] = 'MARKET_ENTRY_DISTANCE_EXCEEDED_LIMIT_PLAN_RETAINED';
                }
                $warnings = array_merge($warnings, array_map(fn (string $warning): string => $symbol.':'.$profile.':'.$warning, $profileWarnings));

                $evidence = [
                    'symbol' => $symbol,
                    'profile' => $profile,
                    'evidence_ids' => ['ict', 'market_regime', 'timeframe_alignment', 'scenarios', 'execution_costs', 'decision'],
                    'ict' => $ictResult,
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
                if ($decision === 'ENTERABLE' && $candidate !== null && ($alignmentResult['allow_entry'] ?? false)) {
                    if ($aiReviewCount < max(0, (int) config('analysis.ai_max_candidates_per_run', 5))) {
                        $aiReview = $this->reviewAi($ai, $validator, $evidence);
                        $aiReviewCount++;
                    } else {
                        $aiReview = ['status' => $this->aiProviderKey() ? 'SKIPPED_LIMIT' : 'NOT_CONFIGURED'];
                    }
                } else {
                    $aiReview = ['status' => $this->aiProviderKey() ? 'NOT_REQUIRED' : 'NOT_CONFIGURED'];
                }
                $aiStatuses[] = $aiReview['status'];

                DB::transaction(function () use ($run, $profile, $symbol, $decision, $timeframes, $evidence, $executionCosts, $aiReview, $profileWarnings, $candidate, $selectedScenario): void {
                    $report = $run->reports()->firstOrCreate(
                        ['profile' => $profile, 'symbol' => $symbol],
                        ['status' => $decision, 'timeframes' => $timeframes + ['states' => $evidence['timeframe_states'], 'alignment' => $evidence['timeframe_alignment'], 'regime' => $evidence['regime'], 'ict' => $evidence['ict'], 'scenarios' => $evidence['scenarios'], 'execution_costs' => $executionCosts], 'reasons' => $evidence['decision_reasons'], 'data_quality_warnings' => $profileWarnings, 'ai_review' => $aiReview]
                    );
                    $opportunity = $report->opportunity()->firstOrCreate(
                        [],
                        ['symbol' => $symbol, 'scenario' => $candidate ? ($selectedScenario['scenario'] ?? null) : null, 'decision' => $decision, 'entry' => $candidate['entry'] ?? null, 'stop' => $candidate['stop'] ?? null, 'target_1' => $candidate['target1'] ?? null, 'target_2' => $candidate['target2'] ?? null, 'snapshot' => $evidence]
                    );
                    foreach (['ict', 'market_regime', 'timeframe_alignment', 'scenarios', 'execution_costs', 'decision'] as $key) {
                        $opportunity->evidence()->firstOrCreate(
                            ['evidence_key' => $key],
                            ['kind' => strtoupper($key), 'payload' => $evidence[$key] ?? $evidence['regime'], 'recorded_at' => now()]
                        );
                    }
                    $opportunity->aiReview()->firstOrCreate(
                        [],
                        ['provider' => $aiReview['metadata']['provider'] ?? ($this->aiProviderKey() ? $this->aiProviderName() : null), 'model' => $aiReview['metadata']['model'] ?? $this->aiProviderModel(), 'status' => $aiReview['status'], 'latency_ms' => $aiReview['latency_ms'] ?? null, 'usage' => $aiReview['metadata']['usage'] ?? null, 'prompt_version' => $aiReview['metadata']['prompt_version'] ?? config('analysis.ai_prompt_version'), 'schema_version' => $aiReview['metadata']['schema_version'] ?? config('analysis.ai_schema_version'), 'evidence_hash' => $aiReview['metadata']['evidence_hash'] ?? null, 'review' => $aiReview['review'] ?? null]
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
            'ai_status' => ! $this->aiProviderKey() ? 'NOT_CONFIGURED' : (in_array('FAILED', $aiStatuses, true) ? 'PARTIAL_FAILURE' : (in_array('REJECTED', $aiStatuses, true) ? 'PARTIAL_REJECTED' : (in_array('COMPLETED', $aiStatuses, true) ? 'COMPLETED' : (in_array('SKIPPED_LIMIT', $aiStatuses, true) ? 'PARTIAL_SKIPPED' : 'NOT_REQUIRED')))),
            'completed_at' => now(),
            'failure_reason' => null,
            'audit' => ['universe_count' => count($universe['symbols']), 'reports_processed' => $profileCount, 'ai_reviews_attempted' => $aiReviewCount, 'source' => 'binance_spot_rest', 'universe_symbols' => $universe['symbols'], 'warnings' => array_values(array_unique($warnings))],
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

    private function aiProviderName(): string
    {
        return (string) config('services.ai.provider', 'openai');
    }

    private function aiProviderKey(): ?string
    {
        return match ($this->aiProviderName()) {
            'openai' => config('services.openai.key'),
            'gemini' => config('services.gemini.key'),
            default => null,
        };
    }

    private function aiProviderModel(): ?string
    {
        return match ($this->aiProviderName()) {
            'openai' => config('services.openai.model'),
            'gemini' => config('services.gemini.model'),
            default => null,
        };
    }

    private function reviewAi(AiAnalysisProvider $ai, AiReviewValidator $validator, array $evidence): array
    {
        if (! $this->aiProviderKey()) {
            return ['status' => 'NOT_CONFIGURED'];
        }

        $startedAt = microtime(true);
        $metadata = [
            'provider' => $this->aiProviderName(),
            'model' => $this->aiProviderModel(),
            'prompt_version' => config('analysis.ai_prompt_version', 'ai-review-v2'),
            'schema_version' => config('analysis.ai_schema_version', 'ai-review-schema-v2'),
            'evidence_hash' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
        ];

        try {
            $result = $ai->review($evidence);
            $review = $result['review'] ?? $result;
            $metadata = array_merge($metadata, $result['metadata'] ?? []);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        } catch (\Throwable $exception) {
            return [
                'status' => 'FAILED',
                'metadata' => $metadata,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }

        return $validator->validate($review, $evidence['evidence_ids'])
            ? ['status' => 'COMPLETED', 'review' => $review, 'metadata' => $metadata, 'latency_ms' => $latencyMs]
            : ['status' => 'REJECTED', 'metadata' => $metadata, 'latency_ms' => $latencyMs];
    }
}
