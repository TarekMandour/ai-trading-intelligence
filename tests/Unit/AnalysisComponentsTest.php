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

    public function test_order_book_costs_use_depth_and_both_execution_legs(): void
    {
        $book = [
            'asks' => [['price' => 100.0, 'quantity' => 0.5], ['price' => 101.0, 'quantity' => 1.0]],
            'bids' => [['price' => 99.0, 'quantity' => 2.0]],
        ];

        $result = (new ExecutionCostEstimator)->estimateFromOrderBook($book, 100.0, 0.001);

        $this->assertTrue($result['available']);
        $this->assertGreaterThan(100.0, $result['buy_vwap']);
        $this->assertSame(99.0, $result['sell_vwap']);
        $this->assertGreaterThan(0.0, $result['fee']);
        $this->assertGreaterThan(0.0, $result['total']);
    }

    public function test_order_book_costs_reject_insufficient_depth(): void
    {
        $book = [
            'asks' => [['price' => 100.0, 'quantity' => 0.1]],
            'bids' => [['price' => 99.0, 'quantity' => 0.1]],
        ];

        $result = (new ExecutionCostEstimator)->estimateFromOrderBook($book, 100.0, 0.001);

        $this->assertFalse($result['available']);
        $this->assertSame('INSUFFICIENT_ASK_DEPTH', $result['reason']);
    }

    public function test_trend_pullback_requires_a_bullish_reclaim_and_volume_confirmation(): void
    {
        $candles = [];
        for ($i = 0; $i < 60; $i++) {
            $close = 100 + ($i * 0.2);
            $candles[] = [
                'open' => $close - 0.05,
                'high' => $close + 0.05,
                'low' => $close - 0.3,
                'close' => $close,
                'volume' => $i === 59 ? 200.0 : 100.0,
            ];
        }

        $results = (new ScenarioEvaluator)->evaluate(['direction' => 'BULLISH', 'behavior' => 'TRENDING', 'volatility' => 'NORMAL'], $candles);
        $trend = collect($results)->firstWhere('scenario', 'TREND_PULLBACK_CONTINUATION');

        $this->assertSame('ENTERABLE', $trend['status']);
        $this->assertGreaterThan($trend['stop'] ?? 0, $trend['candidate']['entry']);
        $this->assertGreaterThan($trend['candidate']['entry'], $trend['candidate']['target1']);
    }

    public function test_scenario_evaluator_does_not_create_levels_without_candle_evidence(): void
    {
        $results = (new ScenarioEvaluator)->evaluate(['direction' => 'BULLISH', 'behavior' => 'TRENDING', 'volatility' => 'NORMAL']);

        $this->assertNull(collect($results)->firstWhere('scenario', 'TREND_PULLBACK_CONTINUATION')['candidate']);
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
