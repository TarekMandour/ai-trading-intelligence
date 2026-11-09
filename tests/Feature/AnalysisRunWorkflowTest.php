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
}
