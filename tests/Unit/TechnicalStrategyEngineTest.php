<?php

namespace Tests\Unit;

use App\Services\Analysis\TechnicalStrategyEngine;
use Tests\TestCase;

class TechnicalStrategyEngineTest extends TestCase
{
    private function candles(int $count = 50, float $price = 100.0): array
    {
        $rows = [];
        $now = now()->getTimestampMs();
        for ($i = 0; $i < $count; $i++) {
            $closeTime = $now - (($count - $i) * 60_000);
            $rows[] = [
                'open_time' => $closeTime - 60_000 + 1,
                'close_time' => $closeTime,
                'open' => $price,
                'high' => $price + 0.5,
                'low' => $price - 0.5,
                'close' => $price,
                'volume' => 100.0,
            ];
        }
        return $rows;
    }

    private function roles(array $candles): array
    {
        return ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles];
    }

    public function test_rejects_missing_timeframe_series(): void
    {
        $result = (new TechnicalStrategyEngine)->evaluate(['context' => $this->candles()], 'Intraday');

        $this->assertSame('REJECTED', $result['decision']);
        $this->assertSame(['MISSING_STRUCTURE_CANDLES'], $result['reasons']);
    }

    public function test_rejects_non_monotonic_candles_instead_of_reordering_them(): void
    {
        $candles = $this->candles();
        $candles[20]['close_time'] = $candles[19]['close_time'];
        $result = (new TechnicalStrategyEngine)->evaluate($this->roles($candles), 'Swing');

        $this->assertSame('REJECTED', $result['decision']);
        $this->assertContains('INSUFFICIENT_CONTEXT_CANDLE_HISTORY', $result['reasons']);
    }

    public function test_forming_candles_are_excluded_before_strategy_calculation(): void
    {
        $candles = $this->candles(40);
        $candles[] = [
            'open_time' => now()->getTimestampMs(),
            'close_time' => now()->addHour()->getTimestampMs(),
            'open' => 100.0, 'high' => 102.0, 'low' => 99.0, 'close' => 101.0, 'volume' => 500.0,
        ];
        $result = (new TechnicalStrategyEngine)->evaluate($this->roles($candles), 'Scalping');

        $this->assertSame('COMPLETED_CANDLES_ONLY', $result['candle_policy']);
        $this->assertNotSame('REJECTED', $result['decision']);
        $this->assertSame('TECHNICAL_STRUCTURE_VOLUME_SR', $result['engine']);
    }

    public function test_choch_counter_preserves_bullish_and_bearish_direction(): void
    {
        $engine = new TechnicalStrategyEngine;
        $method = new \ReflectionMethod($engine, 'detectChoch');
        $candles = [
            ['close' => 100.0], ['close' => 105.0], ['close' => 97.0], ['close' => 94.0],
        ];
        $bearish = [
            ['type' => 'LOW', 'price' => 100.0, 'index' => 0],
            ['type' => 'HIGH', 'price' => 110.0, 'index' => 1],
            ['type' => 'LOW', 'price' => 95.0, 'index' => 2],
            ['type' => 'HIGH', 'price' => 105.0, 'index' => 3],
        ];
        $bullish = [
            ['type' => 'HIGH', 'price' => 110.0, 'index' => 0],
            ['type' => 'LOW', 'price' => 100.0, 'index' => 1],
            ['type' => 'HIGH', 'price' => 115.0, 'index' => 2],
            ['type' => 'LOW', 'price' => 105.0, 'index' => 3],
        ];

        $this->assertSame(-1, $method->invoke($engine, $bearish, $candles));
        $this->assertSame(1, $method->invoke($engine, $bullish, [
            ['close' => 100.0], ['close' => 105.0], ['close' => 112.0], ['close' => 114.0],
        ]));
    }

    public function test_liquidity_sweep_direction_matches_the_swept_swing_side(): void
    {
        $engine = new TechnicalStrategyEngine;
        $method = new \ReflectionMethod($engine, 'detectLiquiditySweep');

        $bearish = $method->invoke($engine, [
            ['high' => 109.0, 'low' => 108.0, 'close' => 108.5],
            ['high' => 111.0, 'low' => 108.0, 'close' => 109.0],
        ], 0, 110.0, 'HIGH');
        $bullish = $method->invoke($engine, [
            ['high' => 92.0, 'low' => 91.0, 'close' => 91.5],
            ['high' => 92.0, 'low' => 89.0, 'close' => 91.0],
        ], 0, 90.0, 'LOW');

        $this->assertSame('BEARISH', $bearish);
        $this->assertSame('BULLISH', $bullish);
    }

    public function test_flat_market_is_analyzed_without_fabricating_trade_levels(): void
    {
        $result = (new TechnicalStrategyEngine)->evaluate($this->roles($this->candles()), 'Intraday');

        $this->assertSame('NO_OPPORTUNITY', $result['decision']);
        $this->assertNull($result['candidate']);
        $this->assertArrayHasKey('opportunity_score', $result['metrics']);
        $this->assertArrayHasKey('supports', $result['levels']);
        $this->assertArrayHasKey('resistances', $result['levels']);
    }
}
