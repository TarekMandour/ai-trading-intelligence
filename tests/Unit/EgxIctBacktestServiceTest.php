<?php

namespace Tests\Unit;

use App\Services\Analysis\IctHistoricalBacktestService;
use App\Services\MarketData\EgxCandleSyncService;
use App\Services\MarketData\EgxIctBacktestService;
use Tests\TestCase;

class EgxIctBacktestServiceTest extends TestCase
{
    private function candles(int $count = 40): array
    {
        $rows = [];
        $now = now()->getTimestampMs();
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'open_time' => $now - (($count - $i) * 60000),
                'close_time' => $now - (($count - $i) * 60000) + 59999,
                'open' => 100.0, 'high' => 101.0, 'low' => 99.0,
                'close' => 100.5, 'volume' => 100.0,
            ];
        }
        return $rows;
    }

    public function test_egx_adapter_uses_profile_timeframes_and_same_chronological_backtest_engine(): void
    {
        $sync = $this->mock(EgxCandleSyncService::class);
        $sync->shouldReceive('candles')->times(4)->andReturnUsing(fn () => $this->candles());

        $backtest = $this->mock(IctHistoricalBacktestService::class);
        $backtest->shouldReceive('runChronologicalSplits')->once()->withArgs(function (array $series, string $profile, array $assumptions): bool {
            return $profile === 'Intraday'
                && array_keys($series) === ['context', 'structure', 'setup', 'entry']
                && $series['context'][0]['open'] === 100.0
                && $assumptions['fee_rate'] === 0.002;
        })->andReturn(['engine' => 'ICT_CHRONOLOGICAL_60_20_20_REPLAY', 'splits' => []]);

        $result = (new EgxIctBacktestService($sync, $backtest))->run('EGX-ASSET-1', 'Intraday', ['fee_rate' => 0.002, 'slippage_bps' => 15]);

        $this->assertSame('COMPLETED', $result['status']);
        $this->assertSame('EGX', $result['market']);
        $this->assertSame('THNDR_HISTORICAL_CANDLES', $result['venue']);
        $this->assertSame(['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'], $result['timeframes']);
    }

    public function test_egx_adapter_rejects_missing_timeframe_history_without_running_backtest(): void
    {
        $sync = $this->mock(EgxCandleSyncService::class);
        $sync->shouldReceive('candles')->once()->andReturn($this->candles(29));
        $backtest = $this->mock(IctHistoricalBacktestService::class);
        $backtest->shouldNotReceive('runChronologicalSplits');

        $result = (new EgxIctBacktestService($sync, $backtest))->run('EGX-ASSET-2', 'Swing', ['fee_rate' => 0.002, 'slippage_bps' => 15]);

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('INSUFFICIENT_HISTORICAL_CANDLES_1w', $result['reason']);
    }
}
