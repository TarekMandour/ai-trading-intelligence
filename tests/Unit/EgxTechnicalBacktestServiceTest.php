<?php

namespace Tests\Unit;

use App\Services\Analysis\TechnicalStrategyEngine;
use App\Services\MarketData\EgxCandleSyncService;
use App\Services\MarketData\EgxTechnicalBacktestService;
use Tests\TestCase;

class EgxTechnicalBacktestServiceTest extends TestCase
{
    private function candles(int $count = 40): array
    {
        $rows = [];
        $now = now()->getTimestampMs();
        for ($i = 0; $i < $count; $i++) {
            $closeTime = $now - (($count - $i) * 60_000);
            $rows[] = [
                'open_time' => $closeTime - 59_999, 'close_time' => $closeTime,
                'open' => 100.0, 'high' => 101.0, 'low' => 99.0, 'close' => 100.5, 'volume' => 100.0,
            ];
        }
        return $rows;
    }

    public function test_backtest_uses_profile_timeframes_and_next_candle_execution_model(): void
    {
        $sync = $this->mock(EgxCandleSyncService::class);
        $sync->shouldReceive('candles')->times(4)->andReturnUsing(fn () => $this->candles());
        $engine = $this->mock(TechnicalStrategyEngine::class);
        $engine->shouldReceive('evaluate')->zeroOrMoreTimes()->andReturn(['decision' => 'NO_OPPORTUNITY', 'candidate' => null]);

        $result = (new EgxTechnicalBacktestService($sync, $engine))->run(
            'EGX-ASSET-1', 'Intraday', ['fee_rate' => 0.002, 'slippage_bps' => 15]
        );

        $this->assertSame('COMPLETED', $result['status']);
        $this->assertSame('EGX', $result['market']);
        $this->assertSame('TECHNICAL_STRATEGY_CHRONOLOGICAL_REPLAY', $result['engine']);
        $this->assertSame(['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'], $result['timeframes']);
        $this->assertSame('NEXT_ENTRY_CANDLE_OPEN', $result['assumptions']['fill_model']);
        $this->assertSame('STOP_FIRST', $result['assumptions']['same_bar_resolution']);
        $this->assertSame(0, $result['counts']['trades']);
    }

    public function test_backtest_sizes_positions_from_risk_budget_and_deducts_fees(): void
    {
        $sync = $this->mock(EgxCandleSyncService::class);
        $sync->shouldReceive('candles')->times(4)->andReturnUsing(fn () => $this->candles(35));
        $engine = $this->mock(TechnicalStrategyEngine::class);
        $engine->shouldReceive('evaluate')->once()->andReturn([
            'decision' => 'ENTERABLE',
            'candidate' => ['stop' => 90.0, 'target1' => 110.0],
            'metrics' => ['opportunity_score' => 80],
        ]);

        $result = (new EgxTechnicalBacktestService($sync, $engine))->run(
            'EGX-ASSET-3',
            'Intraday',
            ['fee_rate' => 0.002, 'slippage_bps' => 15, 'initial_capital' => 100000, 'risk_fraction' => 0.01, 'max_holding_bars' => 100],
        );

        $this->assertSame(1, $result['counts']['trades']);
        $this->assertLessThan(100.0, $result['trades'][0]['quantity']);
        $this->assertEqualsWithDelta(1000.0, $result['trades'][0]['quantity'] * $result['trades'][0]['estimated_risk_per_unit'], 0.01);
        $this->assertGreaterThan(0, $result['trades'][0]['fees']);
        $this->assertSame(100000.0, $result['assumptions']['initial_capital']);
    }

    public function test_backtest_rejects_missing_timeframe_history_without_running_strategy(): void
    {
        $sync = $this->mock(EgxCandleSyncService::class);
        $sync->shouldReceive('candles')->once()->andReturn($this->candles(29));
        $engine = $this->mock(TechnicalStrategyEngine::class);
        $engine->shouldNotReceive('evaluate');

        $result = (new EgxTechnicalBacktestService($sync, $engine))->run(
            'EGX-ASSET-2', 'Swing', ['fee_rate' => 0.002, 'slippage_bps' => 15]
        );

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('INSUFFICIENT_HISTORICAL_CANDLES_1w', $result['reason']);
    }
}
