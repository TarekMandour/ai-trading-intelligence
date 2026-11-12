<?php

namespace Tests\Unit;

use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\ExecutionCostEstimator;
use App\Services\Analysis\IctComprehensiveEngine;
use App\Services\Analysis\MultiTimeframeAlignment;
use App\Services\Analysis\ScenarioEvaluator;
use Tests\TestCase;

class AnalysisComponentsTest extends TestCase
{
    public function test_conflicting_timeframes_are_not_flattened(): void
    {
        $this->assertSame('INVALIDATED', (new MultiTimeframeAlignment)->summarize([
            'context' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'structure' => ['direction' => 'BEARISH', 'behavior' => 'TRENDING'],
            'setup' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'entry' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
        ])['state']);
    }

    public function test_hierarchical_alignment_allows_bearish_setup_pullback_after_bullish_entry_reclaim(): void
    {
        $result = (new MultiTimeframeAlignment)->summarize([
            'context' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'structure' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'setup' => ['direction' => 'BEARISH', 'behavior' => 'RANGING'],
            'entry' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
        ]);

        $this->assertSame('PULLBACK', $result['state']);
        $this->assertTrue($result['allow_entry']);
        $this->assertContains('HEALTHY_PULLBACK', $result['reasons']);
    }

    public function test_hierarchical_alignment_rejects_bearish_context_even_when_lower_timeframes_are_bullish(): void
    {
        $result = (new MultiTimeframeAlignment)->summarize([
            'context' => ['direction' => 'BEARISH', 'behavior' => 'TRENDING'],
            'structure' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'setup' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'entry' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
        ]);

        $this->assertSame('CONFLICTED', $result['state']);
        $this->assertFalse($result['allow_entry']);
    }

    public function test_hierarchical_alignment_requires_bullish_entry_confirmation(): void
    {
        $result = (new MultiTimeframeAlignment)->summarize([
            'context' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'structure' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'setup' => ['direction' => 'BULLISH', 'behavior' => 'TRENDING'],
            'entry' => ['direction' => 'BEARISH', 'behavior' => 'TRENDING'],
        ]);

        $this->assertSame('PARTIAL', $result['state']);
        $this->assertFalse($result['allow_entry']);
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

    public function test_order_book_costs_reject_insufficient_bid_depth(): void
    {
        $book = [
            'asks' => [['price' => 100.0, 'quantity' => 2.0]],
            'bids' => [['price' => 99.0, 'quantity' => 0.1]],
        ];

        $result = (new ExecutionCostEstimator)->estimateFromOrderBook($book, 100.0, 0.001);

        $this->assertFalse($result['available']);
        $this->assertSame('INSUFFICIENT_BID_DEPTH', $result['reason']);
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


    public function test_ai_review_requires_exact_contract_and_bounded_explanations(): void
    {
        $review = [
            'assessment' => 'WEAKEN',
            'timeframe_alignment' => 'PARTIAL',
            'confidence_label' => 'MODERATE',
            'supporting_evidence_ids' => ['known'],
            'contradicting_evidence_ids' => [],
            'missing_confirmations' => ['Wait for a completed entry candle.'],
            'risk_observations' => ['Visible ask depth is limited.'],
            'rationale' => 'The higher timeframe is supportive but entry confirmation is incomplete.',
        ];
        $validator = new AiReviewValidator;

        $this->assertTrue($validator->validate($review, ['known']));
        $this->assertFalse($validator->validate($review + ['unexpected' => 'field'], ['known']));
        $this->assertFalse($validator->validate(array_merge($review, ['missing_confirmations' => array_fill(0, 6, 'item')]), ['known']));
        $this->assertFalse($validator->validate(array_merge($review, ['rationale' => str_repeat('x', 1001)]), ['known']));
        $this->assertFalse($validator->validate(array_merge($review, ['contradicting_evidence_ids' => ['known']]), ['known']));
    }

    public function test_ai_review_rejects_duplicate_and_unknown_evidence_ids(): void
    {
        $review = [
            'assessment' => 'SUPPORT',
            'timeframe_alignment' => 'ALIGNED',
            'confidence_label' => 'HIGH',
            'supporting_evidence_ids' => ['known', 'known'],
            'contradicting_evidence_ids' => [],
            'missing_confirmations' => [],
            'risk_observations' => [],
            'rationale' => 'Evidence is internally consistent.',
        ];

        $this->assertFalse((new AiReviewValidator)->validate($review, ['known']));
        $review['supporting_evidence_ids'] = ['unknown'];
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
    public function test_ict_engine_rejects_missing_timeframe_evidence(): void
    {
        $result = (new IctComprehensiveEngine)->evaluate([], 'Scalping');

        $this->assertSame('REJECTED', $result['decision']);
        $this->assertContains('ICT_DATA_INCOMPLETE_context', $result['reasons']);
    }

    public function test_ict_engine_returns_three_long_only_core_scenarios_for_complete_series(): void
    {
        $candles = [];
        for ($i = 0; $i < 60; $i++) {
            $close = 100 + ($i * 0.1);
            $candles[] = [
                'open_time' => $i * 60000,
                'close_time' => min(now()->getTimestampMs() - (60 - $i) * 60000, now()->getTimestampMs() - 1000),
                'open' => $close - 0.03,
                'high' => $close + 0.2,
                'low' => $close - 0.2,
                'close' => $close,
                'volume' => 100.0,
            ];
        }
        $roles = ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles];
        $result = (new IctComprehensiveEngine)->evaluate($roles, 'Scalping');

        $this->assertSame('ICT_COMPREHENSIVE', $result['engine']);
        $this->assertSame('LONG', $result['direction']);
        $this->assertCount(3, $result['scenarios']);
        $this->assertSame([
            'ICT_01_SELL_SIDE_LIQUIDITY_SWEEP_REVERSAL',
            'ICT_02_BULLISH_FVG_CONTINUATION',
            'ICT_03_BULLISH_ORDER_BLOCK_REACTION',
        ], array_column($result['scenarios'], 'scenario'));
        foreach ($result['scenarios'] as $scenario) {
            $this->assertSame('LONG', $scenario['direction']);
        }
    }

    public function test_ict_engine_rejects_forming_or_non_monotonic_candles(): void
    {
        $candles = [];
        for ($i = 0; $i < 40; $i++) {
            $candles[] = ['open_time' => $i, 'close_time' => $i + 100, 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1.0];
        }
        $roles = ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles];
        $roles['entry'][39]['close_time'] = now()->getTimestampMs() + 1000;

        $result = (new IctComprehensiveEngine)->evaluate($roles, 'Intraday');

        $this->assertSame('REJECTED', $result['decision']);
        $this->assertContains('ICT_DATA_INCOMPLETE_entry', $result['reasons']);
    }

    public function test_ict_entry_planner_exposes_market_and_limit_without_submitting_orders(): void
    {
        $plan = (new \App\Services\Analysis\IctEntryPlanner)->plan(
            100.1,
            ['low' => 99.0, 'high' => 101.0, 'ce' => 100.0],
            true,
            98.0,
        );

        $this->assertSame('MARKET', $plan['market']['entry_type']);
        $this->assertSame('PLANNED', $plan['market']['status']);
        $this->assertSame('LIMIT', $plan['limit']['entry_type']);
        $this->assertSame(100.0, $plan['limit']['limit_price']);
        $this->assertSame('TOUCH_IS_NOT_A_FILL', $plan['limit']['fill_policy']);
        $this->assertFalse($plan['order_submission_enabled']);
    }

    public function test_ict_market_plan_waits_when_price_is_too_far_from_zone(): void
    {
        $plan = (new \App\Services\Analysis\IctEntryPlanner)->plan(
            105.0,
            ['low' => 99.0, 'high' => 101.0, 'ce' => 100.0],
            true,
            98.0,
        );

        $this->assertSame('WAIT', $plan['market']['status']);
        $this->assertSame('PLANNED', $plan['limit']['status']);
    }

    public function test_advanced_ict_evidence_marks_session_models_inactive_without_windows(): void
    {
        $candles = [];
        for ($i = 0; $i < 40; $i++) {
            $candles[] = [
                'open_time' => $i * 60000,
                'close_time' => now()->getTimestampMs() - ((40 - $i) * 60000),
                'open' => 100.0,
                'high' => 101.0,
                'low' => 99.0,
                'close' => 100.5,
                'volume' => 10.0,
            ];
        }

        $evidence = (new \App\Services\Analysis\IctAdvancedEvidenceAnalyzer)->analyze($candles);

        $this->assertSame('INACTIVE_NOT_CONFIGURED', $evidence['session_models']['status']);
        $this->assertSame('NOT_CONFIGURED', $evidence['smt']['status']);
    }

    public function test_ict_fvg_lifecycle_ignores_pre_creation_candles_and_tracks_partial_fill(): void
    {
        $zone = ['low' => 100.0, 'high' => 102.0, 'created_at' => 2000, 'status' => 'ACTIVE'];
        $candles = [
            ['close_time' => 1500, 'low' => 99.0, 'high' => 103.0, 'close' => 101.0],
            ['close_time' => 2500, 'low' => 101.0, 'high' => 104.0, 'close' => 102.5],
        ];

        $result = (new \App\Services\Analysis\IctZoneLifecycleAnalyzer)->bullishFvg($zone, $candles);

        $this->assertSame('PARTIALLY_FILLED', $result['status']);
        $this->assertSame(1, $result['touch_count']);
        $this->assertFalse($result['touch_is_fill']);
    }

    public function test_ict_fvg_lifecycle_distinguishes_full_fill_from_invalidation(): void
    {
        $zone = ['low' => 100.0, 'high' => 102.0, 'created_at' => 2000, 'status' => 'ACTIVE'];
        $candles = [
            ['close_time' => 2500, 'low' => 99.0, 'high' => 103.0, 'close' => 100.5],
            ['close_time' => 3500, 'low' => 98.0, 'high' => 101.0, 'close' => 99.5],
        ];

        $result = (new \App\Services\Analysis\IctZoneLifecycleAnalyzer)->bullishFvg($zone, $candles);

        $this->assertSame('INVALIDATED', $result['status']);
        $this->assertSame(98.0, $result['deepest_price']);
    }

}
