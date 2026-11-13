<?php

namespace Tests\Feature;

use App\Jobs\ProcessAnalysisRun;
use App\Models\AnalysisAuditEvent;
use App\Models\AnalysisRun;
use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\DeterministicAnalysisEngine;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\IctComprehensiveEngine;
use App\Services\MarketData\BinanceSpotClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalysisPersistenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_relationships_persist(): void
    {
        $run = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'COMPLETED', 'configuration_version' => 'test', 'ai_status' => 'NOT_CONFIGURED']);
        $report = $run->reports()->create(['profile' => 'Scalping', 'status' => 'NO_OPPORTUNITY', 'timeframes' => [], 'reasons' => []]);
        $opportunity = $report->opportunity()->create(['decision' => 'NO_OPPORTUNITY', 'snapshot' => ['reason' => 'test']]);
        $opportunity->evidence()->create(['evidence_key' => 'decision', 'kind' => 'DETERMINISTIC', 'payload' => [], 'recorded_at' => now()]);
        $opportunity->aiReview()->create(['status' => 'NOT_CONFIGURED']);
        AnalysisAuditEvent::create(['analysis_run_id' => $run->id, 'analysis_report_id' => $report->id, 'analysis_opportunity_id' => $opportunity->id, 'event' => 'DECISION_RECORDED', 'payload' => [], 'occurred_at' => now()]);
        $this->assertSame('decision', $report->fresh()->opportunity->evidence->sole()->evidence_key);
    }

    public function test_transaction_rolls_back(): void
    {
        $run = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'COMPLETED', 'configuration_version' => 'test', 'ai_status' => 'NOT_CONFIGURED']);
        $report = $run->reports()->create(['profile' => 'Swing', 'status' => 'NO_OPPORTUNITY', 'timeframes' => [], 'reasons' => []]);
        try {
            DB::transaction(function () use ($report): void {
                $report->opportunity()->create(['decision' => 'WAIT', 'snapshot' => []]);
                throw new \RuntimeException;
            });
        } catch (\RuntimeException) {
        } $this->assertDatabaseCount('analysis_opportunities', 0);
    }

    public function test_each_run_keeps_its_own_immutable_snapshot(): void
    {
        $first = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'COMPLETED', 'configuration_version' => 'v1', 'ai_status' => 'NOT_CONFIGURED']);
        $firstReport = $first->reports()->create(['profile' => 'Scalping', 'status' => 'WAIT', 'timeframes' => [], 'reasons' => []]);
        $firstReport->opportunity()->create(['decision' => 'WAIT', 'snapshot' => ['entry' => '100']]);
        $second = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'COMPLETED', 'configuration_version' => 'v2', 'ai_status' => 'NOT_CONFIGURED']);
        $secondReport = $second->reports()->create(['profile' => 'Scalping', 'status' => 'WAIT', 'timeframes' => [], 'reasons' => []]);
        $secondReport->opportunity()->create(['decision' => 'WAIT', 'snapshot' => ['entry' => '200']]);
        $this->assertSame('100', $firstReport->fresh()->opportunity->snapshot['entry']);
    }

    public function test_retrying_the_same_job_does_not_duplicate_relational_records(): void
    {
        $run = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'QUEUED', 'configuration_version' => 'test', 'ai_status' => 'NOT_CONFIGURED']);
        $binance = $this->mock(BinanceSpotClient::class);
        $binance->shouldReceive('eligibleUsdtUniverse')->once()->andReturn([
            'symbols' => [['symbol' => 'BTCUSDT']],
            'tickers' => [],
        ]);
        $candles = array_fill(0, 20, [
            'open_time' => 1, 'close_time' => 2, 'open' => 100.0, 'high' => 101.0,
            'low' => 99.0, 'close' => 100.0, 'volume' => 10.0,
        ]);
        $binance->shouldReceive('candles')->times(7)->andReturn($candles);
        $engine = $this->mock(DeterministicAnalysisEngine::class);
        $engine->shouldReceive('evaluate')->times(12)->andReturn([
            'direction' => 'RANGING',
            'volatility' => 'NORMAL',
            'behavior' => 'CHOPPY',
            'reasons' => ['NO_LONG_SCENARIO_CONFIRMATION'],
            'candidate' => null,
        ]);
        $ai = $this->mock(AiAnalysisProvider::class);
        $job = new ProcessAnalysisRun($run->id);

        $job->handle($binance, $engine, app(ExecutionCostEstimator::class), app(IctComprehensiveEngine::class), $ai, app(AiReviewValidator::class));
        $snapshot = $run->reports()->first()->opportunity->snapshot;
        $run->fresh()->update(['status' => 'QUEUED']);
        $job->handle($binance, $engine, app(ExecutionCostEstimator::class), app(IctComprehensiveEngine::class), $ai, app(AiReviewValidator::class));

        $this->assertDatabaseCount('analysis_reports', 3);
        $this->assertDatabaseCount('analysis_opportunities', 3);
        $this->assertDatabaseCount('analysis_evidence', 18);
        $this->assertDatabaseCount('analysis_ai_reviews', 3);
        $this->assertDatabaseCount('analysis_audit_events', 3);
        $this->assertSame($snapshot, $run->reports()->first()->fresh()->opportunity->snapshot);
    }
}
