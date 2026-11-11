<?php

namespace Tests\Feature;

use App\Jobs\ProcessAnalysisRun;
use App\Models\AnalysisRun;
use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\DeterministicAnalysisEngine;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\MultiTimeframeAlignment;
use App\Services\Analysis\ScenarioEvaluator;
use App\Services\MarketData\BinanceSpotClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalysisRunWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_analysis_creates_a_queued_run_and_dispatches_a_job(): void
    {
        Queue::fake();

        $this->post('/analysis-runs')->assertRedirect();

        $this->assertDatabaseHas('analysis_runs', ['status' => 'QUEUED', 'ai_status' => 'NOT_CONFIGURED']);
        Queue::assertPushed(ProcessAnalysisRun::class);
    }

    public function test_manual_analysis_uses_the_selected_gemini_provider_configuration(): void
    {
        Queue::fake();

        config([
            'services.ai.provider' => 'gemini',
            'services.gemini.key' => 'test-gemini-key',
            'services.openai.key' => null,
        ]);

        $this->post('/analysis-runs')->assertRedirect();

        $this->assertDatabaseHas('analysis_runs', [
            'status' => 'QUEUED',
            'ai_status' => 'PENDING',
        ]);
        Queue::assertPushed(ProcessAnalysisRun::class);
    }

    public function test_enterable_candidate_fetches_order_book_and_uses_net_execution_costs(): void
    {
        config([
            'analysis.profiles' => ['Test' => [
                'context' => '1h',
                'structure' => '15m',
                'setup' => '5m',
                'entry' => '1m',
            ]],
            'analysis.trade_notional' => 100,
            'analysis.fee_rate' => 0.001,
            'analysis.minimum_net_risk_reward' => 1.5,
            'analysis.ai_max_candidates_per_run' => 0,
        ]);

        $run = AnalysisRun::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'QUEUED',
            'configuration_version' => 'test',
            'ai_status' => 'NOT_CONFIGURED',
            'audit' => ['universe_symbols' => [['symbol' => 'BTCUSDT']]],
        ]);

        $candles = array_fill(0, 20, [
            'open' => 100.0,
            'high' => 101.0,
            'low' => 99.0,
            'close' => 100.5,
            'volume' => 100.0,
        ]);

        $binance = $this->mock(BinanceSpotClient::class);
        $binance->shouldReceive('candles')->times(4)->andReturn($candles);
        $binance->shouldReceive('orderBook')->once()->with('BTCUSDT')->andReturn([
            'asks' => [['price' => 101.0, 'quantity' => 2.0]],
            'bids' => [['price' => 99.0, 'quantity' => 2.0]],
            'last_update_id' => 123,
        ]);

        $engine = $this->mock(DeterministicAnalysisEngine::class);
        $engine->shouldReceive('evaluate')->times(4)->andReturn([
            'direction' => 'BULLISH',
            'behavior' => 'TRENDING',
            'volatility' => 'NORMAL',
            'reasons' => ['TEST_BULLISH_REGIME'],
        ]);

        $scenarios = $this->mock(ScenarioEvaluator::class);
        $scenarios->shouldReceive('evaluate')->once()->andReturn([
            [
                'scenario' => 'TEST_BREAKOUT',
                'status' => 'ENTERABLE',
                'candidate' => ['entry' => 100.0, 'stop' => 95.0, 'target1' => 125.0, 'target2' => 130.0],
                'reasons' => ['TEST_SETUP_CONFIRMED'],
            ],
        ]);

        $ai = $this->mock(AiAnalysisProvider::class);

        (new ProcessAnalysisRun($run->id))->handle(
            $binance,
            $engine,
            app(ExecutionCostEstimator::class),
            app(MultiTimeframeAlignment::class),
            $scenarios,
            $ai,
            app(AiReviewValidator::class),
        );

        $report = $run->fresh()->reports()->where('profile', 'Test')->where('symbol', 'BTCUSDT')->firstOrFail();
        $this->assertSame('ENTERABLE', $report->status);
        $this->assertSame(true, $report->timeframes['execution_costs']['available']);
        $this->assertSame('TEST_BREAKOUT', $report->opportunity->scenario);
        $this->assertGreaterThan(1.5, $report->timeframes['execution_costs']['net_risk_reward']);
        $this->assertSame('COMPLETED', $run->fresh()->status);
    }

    public function test_enterable_candidate_waits_when_order_book_depth_is_insufficient(): void
    {
        config([
            'analysis.profiles' => ['Test' => [
                'context' => '1h',
                'structure' => '15m',
                'setup' => '5m',
                'entry' => '1m',
            ]],
            'analysis.trade_notional' => 100,
            'analysis.fee_rate' => 0.001,
            'analysis.minimum_net_risk_reward' => 1.5,
            'analysis.ai_max_candidates_per_run' => 0,
        ]);

        $run = AnalysisRun::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'QUEUED',
            'configuration_version' => 'test',
            'ai_status' => 'NOT_CONFIGURED',
            'audit' => ['universe_symbols' => [['symbol' => 'BTCUSDT']]],
        ]);

        $candles = array_fill(0, 20, [
            'open' => 100.0,
            'high' => 101.0,
            'low' => 99.0,
            'close' => 100.5,
            'volume' => 100.0,
        ]);

        $binance = $this->mock(BinanceSpotClient::class);
        $binance->shouldReceive('candles')->times(4)->andReturn($candles);
        $binance->shouldReceive('orderBook')->once()->with('BTCUSDT')->andReturn([
            'asks' => [['price' => 101.0, 'quantity' => 0.1]],
            'bids' => [['price' => 99.0, 'quantity' => 0.1]],
            'last_update_id' => 124,
        ]);

        $engine = $this->mock(DeterministicAnalysisEngine::class);
        $engine->shouldReceive('evaluate')->times(4)->andReturn([
            'direction' => 'BULLISH',
            'behavior' => 'TRENDING',
            'volatility' => 'NORMAL',
            'reasons' => ['TEST_BULLISH_REGIME'],
        ]);

        $scenarios = $this->mock(ScenarioEvaluator::class);
        $scenarios->shouldReceive('evaluate')->once()->andReturn([
            [
                'scenario' => 'TEST_BREAKOUT',
                'status' => 'ENTERABLE',
                'candidate' => ['entry' => 100.0, 'stop' => 95.0, 'target1' => 125.0, 'target2' => 130.0],
                'reasons' => ['TEST_SETUP_CONFIRMED'],
            ],
        ]);

        $ai = $this->mock(AiAnalysisProvider::class);

        (new ProcessAnalysisRun($run->id))->handle(
            $binance,
            $engine,
            app(ExecutionCostEstimator::class),
            app(MultiTimeframeAlignment::class),
            $scenarios,
            $ai,
            app(AiReviewValidator::class),
        );

        $report = $run->fresh()->reports()->where('profile', 'Test')->where('symbol', 'BTCUSDT')->firstOrFail();
        $this->assertSame('WAIT', $report->status);
        $this->assertFalse($report->timeframes['execution_costs']['available']);
        $this->assertSame('INSUFFICIENT_ASK_DEPTH', $report->timeframes['execution_costs']['reason']);
        $this->assertSame('TEST_BREAKOUT', $report->opportunity->scenario);
        $this->assertSame('COMPLETED', $run->fresh()->status);
    }

    public function test_job_keeps_run_retryable_when_market_data_is_unavailable(): void
    {
        $run = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'QUEUED', 'configuration_version' => 'test', 'ai_status' => 'NOT_CONFIGURED']);
        $client = $this->mock(BinanceSpotClient::class);
        $client->shouldReceive('eligibleUsdtUniverse')->once()->andThrow(new \RuntimeException('offline'));

        $ai = $this->mock(AiAnalysisProvider::class);
        try {
            (new ProcessAnalysisRun($run->id))->handle($client, app(DeterministicAnalysisEngine::class), app(ExecutionCostEstimator::class), app(MultiTimeframeAlignment::class), app(ScenarioEvaluator::class), $ai, app(AiReviewValidator::class));
            $this->fail('Expected the unavailable market-data exception to be rethrown for queue retry handling.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('offline', $exception->getMessage());
        }

        $this->assertDatabaseHas('analysis_runs', ['id' => $run->id, 'status' => 'RUNNING', 'failure_reason' => 'BINANCE_UNAVAILABLE']);
        $this->assertDatabaseCount('analysis_reports', 0);
        $this->get("/analysis-runs/{$run->id}")->assertOk()->assertSee('BINANCE_UNAVAILABLE');
    }

    public function test_ai_review_is_advisory_and_cannot_change_deterministic_decision_or_levels(): void
    {
        config([
            'analysis.profiles' => ['Test' => [
                'context' => '1h',
                'structure' => '15m',
                'setup' => '5m',
                'entry' => '1m',
            ]],
            'analysis.trade_notional' => 100,
            'analysis.fee_rate' => 0.001,
            'analysis.minimum_net_risk_reward' => 1.5,
            'analysis.ai_max_candidates_per_run' => 1,
            'services.openai.key' => 'test-key',
        ]);

        $run = AnalysisRun::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'QUEUED',
            'configuration_version' => 'test',
            'ai_status' => 'PENDING',
            'audit' => ['universe_symbols' => [['symbol' => 'BTCUSDT']]],
        ]);

        $candles = array_fill(0, 20, [
            'open' => 100.0,
            'high' => 101.0,
            'low' => 99.0,
            'close' => 100.5,
            'volume' => 100.0,
        ]);

        $binance = $this->mock(BinanceSpotClient::class);
        $binance->shouldReceive('candles')->times(4)->andReturn($candles);
        $binance->shouldReceive('orderBook')->once()->with('BTCUSDT')->andReturn([
            'asks' => [['price' => 101.0, 'quantity' => 2.0]],
            'bids' => [['price' => 99.0, 'quantity' => 2.0]],
            'last_update_id' => 125,
        ]);

        $engine = $this->mock(DeterministicAnalysisEngine::class);
        $engine->shouldReceive('evaluate')->times(4)->andReturn([
            'direction' => 'BULLISH',
            'behavior' => 'TRENDING',
            'volatility' => 'NORMAL',
            'reasons' => ['TEST_BULLISH_REGIME'],
        ]);

        $scenarios = $this->mock(ScenarioEvaluator::class);
        $scenarios->shouldReceive('evaluate')->once()->andReturn([[
            'scenario' => 'TEST_BREAKOUT',
            'status' => 'ENTERABLE',
            'candidate' => ['entry' => 100.0, 'stop' => 95.0, 'target1' => 125.0, 'target2' => 130.0],
            'reasons' => ['TEST_SETUP_CONFIRMED'],
        ]]);

        $ai = $this->mock(AiAnalysisProvider::class);
        $ai->shouldReceive('review')->once()->andReturn([
            'review' => [
                'assessment' => 'WEAKEN',
                'timeframe_alignment' => 'PARTIAL',
                'confidence_label' => 'LOW',
                'supporting_evidence_ids' => ['market_regime'],
                'contradicting_evidence_ids' => ['execution_costs'],
                'missing_confirmations' => ['Wait for stronger entry confirmation.'],
                'risk_observations' => ['The AI review is cautious.'],
                'rationale' => 'The deterministic setup passes, but the supplied evidence contains a caution.',
            ],
            'metadata' => ['model' => 'test-model', 'usage' => ['total_tokens' => 42]],
        ]);

        (new ProcessAnalysisRun($run->id))->handle(
            $binance,
            $engine,
            app(ExecutionCostEstimator::class),
            app(MultiTimeframeAlignment::class),
            $scenarios,
            $ai,
            app(AiReviewValidator::class),
        );

        $report = $run->fresh()->reports()->where('profile', 'Test')->where('symbol', 'BTCUSDT')->firstOrFail();
        $review = $report->opportunity->aiReview;

        $this->assertSame('ENTERABLE', $report->status);
        $this->assertSame('ENTERABLE', $report->opportunity->decision);
        $this->assertSame('100.00000000', $report->opportunity->entry);
        $this->assertSame('95.00000000', $report->opportunity->stop);
        $this->assertSame('125.00000000', $report->opportunity->target_1);
        $this->assertSame('COMPLETED', $review->status);
        $this->assertSame('WEAKEN', $review->review['assessment']);
        $this->assertSame('ai-review-v2', $review->prompt_version);
        $this->assertSame('ai-review-schema-v2', $review->schema_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $review->evidence_hash);
        $this->assertSame('COMPLETED', $run->fresh()->status);
    }

    public function test_ai_provider_failure_does_not_change_deterministic_enterable_report(): void
    {
        config([
            'analysis.profiles' => ['Test' => [
                'context' => '1h',
                'structure' => '15m',
                'setup' => '5m',
                'entry' => '1m',
            ]],
            'analysis.trade_notional' => 100,
            'analysis.fee_rate' => 0.001,
            'analysis.minimum_net_risk_reward' => 1.5,
            'analysis.ai_max_candidates_per_run' => 1,
            'services.openai.key' => 'test-key',
        ]);

        $run = AnalysisRun::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'QUEUED',
            'configuration_version' => 'test',
            'ai_status' => 'PENDING',
            'audit' => ['universe_symbols' => [['symbol' => 'BTCUSDT']]],
        ]);
        $candles = array_fill(0, 20, ['open' => 100.0, 'high' => 101.0, 'low' => 99.0, 'close' => 100.5, 'volume' => 100.0]);

        $binance = $this->mock(BinanceSpotClient::class);
        $binance->shouldReceive('candles')->times(4)->andReturn($candles);
        $binance->shouldReceive('orderBook')->once()->with('BTCUSDT')->andReturn([
            'asks' => [['price' => 101.0, 'quantity' => 2.0]],
            'bids' => [['price' => 99.0, 'quantity' => 2.0]],
            'last_update_id' => 126,
        ]);
        $engine = $this->mock(DeterministicAnalysisEngine::class);
        $engine->shouldReceive('evaluate')->times(4)->andReturn([
            'direction' => 'BULLISH', 'behavior' => 'TRENDING', 'volatility' => 'NORMAL', 'reasons' => ['TEST_BULLISH_REGIME'],
        ]);
        $scenarios = $this->mock(ScenarioEvaluator::class);
        $scenarios->shouldReceive('evaluate')->once()->andReturn([[
            'scenario' => 'TEST_BREAKOUT', 'status' => 'ENTERABLE',
            'candidate' => ['entry' => 100.0, 'stop' => 95.0, 'target1' => 125.0, 'target2' => 130.0],
            'reasons' => ['TEST_SETUP_CONFIRMED'],
        ]]);
        $ai = $this->mock(AiAnalysisProvider::class);
        $ai->shouldReceive('review')->once()->andThrow(new \RuntimeException('provider unavailable'));

        (new ProcessAnalysisRun($run->id))->handle(
            $binance,
            $engine,
            app(ExecutionCostEstimator::class),
            app(MultiTimeframeAlignment::class),
            $scenarios,
            $ai,
            app(AiReviewValidator::class),
        );

        $report = $run->fresh()->reports()->where('profile', 'Test')->where('symbol', 'BTCUSDT')->firstOrFail();
        $this->assertSame('ENTERABLE', $report->status);
        $this->assertSame('FAILED', $report->opportunity->aiReview->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $report->opportunity->aiReview->evidence_hash);
        $this->assertSame('COMPLETED', $run->fresh()->status);
    }

}
