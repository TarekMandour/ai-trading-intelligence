<?php

namespace Tests\Unit;

use App\Services\Ai\AiReviewValidator;
use App\Services\Analysis\ExecutionCostEstimator;
use Tests\TestCase;

class AnalysisComponentsTest extends TestCase
{
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

    public function test_ai_review_rejects_unknown_evidence(): void
    {
        $this->assertSame(false, (new AiReviewValidator)->validate(['assessment' => 'ABSTAIN', 'timeframe_alignment' => 'ALIGNED', 'supporting_evidence_ids' => ['bad'], 'contradicting_evidence_ids' => [], 'rationale' => 'x'], ['known']));
    }
}
