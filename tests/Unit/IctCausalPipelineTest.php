<?php

namespace Tests\\Unit;

use App\\Services\\Analysis\\IctDealingRangeAnalyzer;
use App\\Services\\Analysis\\IctDisplacementAnalyzer;
use App\\Services\\Analysis\\IctLiquidityAnalyzer;
use App\\Services\\Analysis\\IctMarketStructureAnalyzer;
use App\\Services\\Analysis\\IctStructuralTradePlanner;
use App\\Services\\Analysis\\IctSwingPointDetector;
use App\\Services\\Analysis\\IctZoneQualityAnalyzer;
use Tests\\TestCase;

class IctCausalPipelineTest extends TestCase
{
    private function candles(array $highs, array $lows, ?array $closes = null, ?array $volumes = null): array
    {
        $rows = [];
        foreach ($highs as $i => $high) {
            $close = $closes[$i] ?? (($high + $lows[$i]) / 2);
            $rows[] = [
                'open_time' => $i * 60000,
                'close_time' => ($i + 1) * 60000 - 1,
                'open' => min($close, ($high + $lows[$i]) / 2),
                'high' => (float) $high,
                'low' => (float) $lows[$i],
                'close' => (float) $close,
                'volume' => (float) ($volumes[$i] ?? 100),
            ];
        }
        return $rows;
    }

    public function test_comprehensive_engine_exposes_causal_pipeline_as_authoritative_plan(): void
    {
        $candles = [];
        $now = now()->getTimestampMs();
        for ($i = 0; $i < 60; $i++) {
            $close = 100.0 + sin($i / 2) * 2;
            $candles[] = [
                'open_time' => $now - ((60 - $i) * 60000),
                'close_time' => $now - ((60 - $i) * 60000) + 59999,
                'open' => $close - 0.1,
                'high' => $close + 0.5,
                'low' => $close - 0.5,
                'close' => $close,
                'volume' => 100.0,
            ];
        }
        $roles = ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles];

        $result = (new \\App\\Services\\Analysis\\IctComprehensiveEngine)->evaluate($roles, 'Scalping');

        $this->assertSame('ict-v2-causal', $result['version']);
        $this->assertArrayHasKey('market_structure', $result['structural_analysis']);
        $this->assertArrayHasKey('liquidity', $result['structural_analysis']);
        $this->assertArrayHasKey('displacement', $result['structural_analysis']);
        $this->assertArrayHasKey('zones', $result['structural_analysis']);
        $this->assertArrayHasKey('dealing_range', $result['structural_analysis']);
        $this->assertArrayHasKey('structural_trade_plan', $result);
        $this->assertSame('ICT_CAUSAL_STRUCTURE_LIQUIDITY', $result['canonical_scenario']['scenario']);
        $this->assertFalse($result['structural_trade_plan']['order_submission_enabled']);
    }

    public function test_swing_points_are_only_available_after_right_side_confirmation(): void
    {
        config(['ict.swing_left' => 1, 'ict.swing_right' => 2]);
        $candles = $this->candles(
            [10, 11, 15, 12, 11, 10],
            [8, 9, 10, 9, 8, 7],
        );

        $swings = (new IctSwingPointDetector)->detect($candles);

        $pivot = collect($swings['highs'])->firstWhere('index', 2);
        $this->assertNotNull($pivot);
        $this->assertSame(4, $pivot['confirmation_index']);
        $this->assertSame(4 * 60000 + 59999, $pivot['confirmed_at']);
        $this->assertSame('STRICT_FRACTAL_CONFIRMED_AFTER_RIGHT_BARS', $swings['method']);
    }

    public function test_market_structure_records_bos_only_after_confirmed_pivot_and_close_break(): void
    {
        config(['ict.swing_left' => 1, 'ict.swing_right' => 1]);
        $candles = $this->candles(
            [10, 12, 11, 10, 11, 13, 14, 13],
            [8, 9, 10, 9, 8, 9, 10, 9],
            [9, 11, 10, 9.5, 10.5, 12.5, 13.5, 12.5],
        );

        $result = (new IctMarketStructureAnalyzer)->analyze($candles);

        $this->assertNotEmpty($result['events']);
        $this->assertSame('BOS_BULLISH', $result['events'][0]['type']);
        $this->assertGreaterThan($result['events'][0]['pivot_confirmed_at_index'], $result['events'][0]['break_index'] - 1);
        $this->assertSame('BULLISH', $result['bias']);
    }

    public function test_liquidity_sweep_requires_equal_confirmed_levels_and_wick_reclaim_close(): void
    {
        config(['ict.swing_left' => 1, 'ict.swing_right' => 1, 'ict.equal_level_tolerance_bps' => 10]);
        $candles = $this->candles(
            [11, 11, 11, 11, 11, 11, 11, 11, 11, 11],
            [10.5, 10.4, 10.0, 10.4, 10.5, 10.4, 10.0, 10.4, 10.5, 9.0],
            [10.7, 10.8, 10.5, 10.8, 10.7, 10.8, 10.5, 10.8, 10.7, 10.6],
        );
        $swings = (new IctSwingPointDetector)->detect($candles);
        $result = (new IctLiquidityAnalyzer)->analyze($candles, $swings);

        $this->assertNotEmpty($result['pools']);
        $this->assertNotNull($result['latest_bullish_sweep']);
        $this->assertSame('SSL', $result['latest_bullish_sweep']['side']);
        $this->assertSame(9, $result['latest_bullish_sweep']['index']);
        $this->assertSame('WICK_THROUGH_AND_CLOSE_BACK_INSIDE', $result['latest_bullish_sweep']['confirmation']);
    }

    public function test_displacement_uses_prior_atr_and_prior_volume_baselines(): void
    {
        config([
            'ict.atr_period' => 14,
            'ict.volume_period' => 20,
            'ict.displacement_atr_multiple' => 1.2,
            'ict.displacement_body_ratio' => 0.65,
            'ict.displacement_volume_multiple' => 1.5,
        ]);
        $highs = array_fill(0, 30, 101.0);
        $lows = array_fill(0, 30, 100.0);
        $closes = array_fill(0, 30, 100.2);
        $volumes = array_fill(0, 30, 100.0);
        $highs[29] = 104.0; $lows[29] = 100.0; $closes[29] = 103.7; $volumes[29] = 300.0;
        $result = (new IctDisplacementAnalyzer)->analyze($this->candles($highs, $lows, $closes, $volumes));

        $this->assertNotNull($result['latest_bullish']);
        $this->assertSame(29, $result['latest_bullish']['index']);
        $this->assertGreaterThan(1.2, $result['latest_bullish']['range_atr']);
        $this->assertSame(3.0, $result['latest_bullish']['volume_ratio']);
    }

    public function test_zone_quality_links_fvg_to_displacement_and_replays_invalidation(): void
    {
        config(['ict.minimum_zone_quality' => 45, 'ict.atr_period' => 14, 'ict.volume_period' => 20]);
        $highs = array_fill(0, 35, 101.0);
        $lows = array_fill(0, 35, 100.0);
        $closes = array_fill(0, 35, 100.2);
        $volumes = array_fill(0, 35, 100.0);
        $highs[29] = 100.5; $lows[29] = 99.8; $closes[29] = 100.0;
        $highs[30] = 105.0; $lows[30] = 100.2; $closes[30] = 104.8; $volumes[30] = 300.0;
        $highs[31] = 104.0; $lows[31] = 102.0; $closes[31] = 103.0;
        $highs[32] = 103.0; $lows[32] = 99.0; $closes[32] = 99.5;
        $candles = $this->candles($highs, $lows, $closes, $volumes);
        $displacements = (new IctDisplacementAnalyzer)->analyze($candles);
        $structure = (new IctMarketStructureAnalyzer)->analyze($candles);
        $liquidity = ['sweeps' => [], 'latest_bullish_sweep' => null];
        $zones = (new IctZoneQualityAnalyzer)->analyze($candles, $displacements, $structure, $liquidity);

        $this->assertNotEmpty($zones['zones']);
        $fvg = collect($zones['zones'])->firstWhere('type', 'BULLISH_FVG');
        $this->assertNotNull($fvg);
        $this->assertSame(30, $fvg['impulse_index']);
        $this->assertGreaterThanOrEqual(45, $fvg['quality_score']);
        $this->assertSame('INVALIDATED', $fvg['lifecycle']);
    }

    public function test_dealing_range_uses_latest_confirmed_bullish_leg_and_midpoint(): void
    {
        config(['ict.swing_left' => 1, 'ict.swing_right' => 1]);
        $candles = $this->candles(
            [101, 102, 101, 103, 105, 104, 106, 105],
            [99, 98, 100, 101, 102, 101, 103, 104],
            [100, 100, 101, 102, 104, 103, 105, 104.5],
        );
        $structure = ['bias' => 'BULLISH', 'swings' => (new IctSwingPointDetector)->detect($candles)];
        $range = (new IctDealingRangeAnalyzer)->analyze($candles, $structure);

        $this->assertSame('AVAILABLE', $range['status']);
        $this->assertGreaterThan($range['low'], $range['high']);
        $this->assertEqualsWithDelta(($range['low'] + $range['high']) / 2, $range['equilibrium'], 0.000001);
        $this->assertContains($range['location'], ['DISCOUNT', 'PREMIUM', 'EQUILIBRIUM']);
    }

    public function test_structural_plan_requires_zone_touch_and_entry_timeframe_breakout(): void
    {
        $setup = $this->candles([101, 102, 101, 103, 102, 104], [99, 100, 98, 100, 99, 101]);
        $entry = [
            ['open' => 102.0, 'high' => 103.0, 'low' => 101.0, 'close' => 102.5, 'close_time' => 700000],
            ['open' => 102.5, 'high' => 104.0, 'low' => 100.5, 'close' => 103.5, 'close_time' => 760000],
        ];
        $structure = ['swings' => ['lows' => [['index' => 2, 'confirmation_index' => 3, 'price' => 98.0]], 'highs' => [], 'broken_levels' => ['highs' => []]]];
        $zones = ['best_bullish_zone' => ['type' => 'BULLISH_FVG', 'low' => 100.0, 'high' => 102.0, 'ce' => 101.0, 'created_at' => 600000, 'quality_score' => 75]];
        $plan = (new IctStructuralTradePlanner)->plan($setup, $structure, ['pools' => []], ['latest_bullish' => ['atr' => 1.0]], $zones, [], $entry);

        $this->assertSame('ENTERABLE', $plan['status']);
        $this->assertGreaterThan($plan['stop_loss'], $plan['entry']);
        $this->assertGreaterThan($plan['entry'], $plan['target1']);
        $this->assertGreaterThan($plan['target1'], $plan['target2']);
        $this->assertFalse($plan['order_submission_enabled']);
    }
}
