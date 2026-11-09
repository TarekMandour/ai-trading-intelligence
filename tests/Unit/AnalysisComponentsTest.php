<?php

namespace Tests\Unit;

use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\MultiTimeframeAlignment;
use App\Services\Analysis\ScenarioEvaluator;
use PHPUnit\Framework\TestCase;

class AnalysisComponentsTest extends TestCase
{
    public function test_conflicting_timeframes_are_not_flattened(): void
    {
        $this->assertSame('CONFLICTED', (new MultiTimeframeAlignment)->summarize(['a' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'], 'b' => ['direction' => 'BEARISH', 'behavior' => 'TRENDING'], 'c' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'], 'd' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING']])['state']);
    }

    public function test_missing_order_book_does_not_invent_costs(): void
    {
        $this->assertSame(false, (new ExecutionCostEstimator)->estimate(null, null, 100)['available']);
    }

    public function test_all_scenarios_are_evaluated(): void
    {
        $this->assertCount(4, (new ScenarioEvaluator)->evaluate(['direction' => 'BULLISH', 'behavior' => 'TRENDING', 'volatility' => 'HIGH']));
    }

    public function test_ai_review_rejects_unknown_evidence(): void
    {
        $this->assertSame(false, (new AiReviewValidator)->validate(['assessment' => 'ABSTAIN', 'timeframe_alignment' => 'ALIGNED', 'supporting_evidence_ids' => ['bad'], 'contradicting_evidence_ids' => [], 'rationale' => 'x'], ['known']));
    }
}
