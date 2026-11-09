<?php

namespace Tests\Feature;

use App\Jobs\ProcessAnalysisRun;
use App\Models\AnalysisRun;
use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\DeterministicAnalysisEngine;
use App\Services\Analysis\ExecutionCostEstimator;
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

    public function test_job_persists_three_no_opportunity_reports_when_market_data_is_unavailable(): void
    {
        $run = AnalysisRun::create(['idempotency_key' => (string) Str::uuid(), 'status' => 'QUEUED', 'configuration_version' => 'test', 'ai_status' => 'NOT_CONFIGURED']);
        $client = $this->mock(BinanceSpotClient::class);
        $client->shouldReceive('eligibleUsdtUniverse')->once()->andThrow(new \RuntimeException('offline'));

        $ai = $this->mock(AiAnalysisProvider::class);
        (new ProcessAnalysisRun($run->id))->handle($client, app(DeterministicAnalysisEngine::class), app(ExecutionCostEstimator::class), $ai, app(AiReviewValidator::class));

        $this->assertDatabaseHas('analysis_runs', ['id' => $run->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseCount('analysis_reports', 3);
        $this->get("/analysis-runs/{$run->id}")->assertOk()->assertSee('Scalping Report')->assertSee('Intraday Report')->assertSee('Swing Report');
    }
}
