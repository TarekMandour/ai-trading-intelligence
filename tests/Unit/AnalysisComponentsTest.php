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
        $this->assertEqualsWithDelta(0.1, $result['entry_fee'], 0.000001);
        $this->assertGreaterThan(0.0, $result['exit_fee']);
        $this->assertGreaterThan(0.0, $result['entry_cost']);
        $this->assertGreaterThan(0.0, $result['exit_cost']);
        $this->assertEqualsWithDelta($result['entry_cost'] + $result['exit_cost'], $result['total'], 0.000001);
        $this->assertEqualsWithDelta($result['entry_cost_per_unit'] + $result['exit_cost_per_unit'], $result['total'] / $result['base_quantity'], 0.000001);
    }

    public function test_net_risk_reward_does_not_double_count_entry_spread_or_impact(): void
    {
        $estimator = new ExecutionCostEstimator;
        $book = [
            'asks' => [['price' => 101.0, 'quantity' => 2.0]],
            'bids' => [['price' => 99.0, 'quantity' => 2.0]],
        ];
        $costs = $estimator->estimateFromOrderBook($book, 100.0, 0.001);
        $candidate = ['entry' => 100.0, 'stop' => 95.0, 'target1' => 110.0];

        $metrics = $estimator->netRiskReward($candidate, $costs);
        $expectedEntryFeePerUnit = $costs['entry_fee'] / $costs['base_quantity'];
        $expectedRoundTrip = $expectedEntryFeePerUnit + $costs['exit_cost_per_unit'];

        $this->assertEqualsWithDelta($costs['buy_vwap'], $metrics['estimated_entry'], 0.000001);
        $this->assertEqualsWithDelta($expectedRoundTrip, $metrics['round_trip_cost_per_unit'], 0.000001);
        $this->assertNotEqualsWithDelta($costs['entry_cost_per_unit'] + $costs['exit_cost_per_unit'], $metrics['round_trip_cost_per_unit'], 0.000001);
        $this->assertEqualsWithDelta(9.0 - $expectedRoundTrip, $metrics['net_target1_reward_per_unit'], 0.000001);
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
        $this->assertGreaterThan($trend['candidate']['stop'], $trend['candidate']['entry']);
        $this->assertGreaterThan($trend['candidate']['entry'], $trend['candidate']['target1']);
    }

    public function test_scenario_evaluator_does_not_create_levels_without_candle_evidence(): void
    {
        $results = (new ScenarioEvaluator)->evaluate(['direction' => 'BULLISH', 'behavior' => 'TRENDING', 'volatility' => 'NORMAL']);

        $this->assertNull(collect($results)->firstWhere('scenario', 'TREND_PULLBACK_CONTINUATION')['candidate']);
    }

    public function test_ai_review_rejects_malformed_evidence_id_collections(): void
    {
        $review = [
            'assessment' => 'ABSTAIN',
            'timeframe_alignment' => 'INSUFFICIENT',
            'supporting_evidence_ids' => 'not-an-array',
            'contradicting_evidence_ids' => [],
            'rationale' => 'Insufficient evidence.',
        ];

        $this->assertFalse((new AiReviewValidator)->validate($review, ['known']));
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
