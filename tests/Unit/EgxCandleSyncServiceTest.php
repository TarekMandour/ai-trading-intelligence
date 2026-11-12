<?php

namespace Tests\\Unit;

use App\\Services\\MarketData\\EgxCandleSyncService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use ReflectionMethod;
use Tests\\TestCase;

class EgxCandleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_hour_egx_candle_requires_four_contiguous_closed_hourly_candles(): void
    {
        $assetId = 'egx-test-asset';
        $bucket = (int) (floor(1700000000 / 14400) * 14400) * 1000;

        $this->insertHourlyCandles($assetId, $bucket, [0, 1]);

        $service = new EgxCandleSyncService;
        $aggregate = new ReflectionMethod($service, 'aggregate');
        $aggregate->invoke($service, $assetId, '1h', '4h');

        $this->assertSame(0, DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', '4h')->count());

        $this->insertHourlyCandles($assetId, $bucket, [2, 3]);
        $aggregate->invoke($service, $assetId, '1h', '4h');

        $candle = DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', '4h')->first();

        $this->assertNotNull($candle);
        $this->assertSame($bucket, (int) $candle->open_time);
        $this->assertSame($bucket + (4 * 60 * 60 * 1000) - 1, (int) $candle->close_time);
        $this->assertSame(4, (int) DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', '4h')->count());
    }

    public function test_four_hour_egx_candle_rejects_a_gap_even_when_four_rows_exist(): void
    {
        $assetId = 'egx-gap-test-asset';
        $bucket = (int) (floor(1700000000 / 14400) * 14400) * 1000;

        $this->insertHourlyCandles($assetId, $bucket, [0, 1, 2, 4]);

        $service = new EgxCandleSyncService;
        $aggregate = new ReflectionMethod($service, 'aggregate');
        $aggregate->invoke($service, $assetId, '1h', '4h');

        $this->assertSame(0, DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', '4h')->count());
    }

    private function insertHourlyCandles(string $assetId, int $bucket, array $indices): void
    {
        foreach ($indices as $index) {
            $openTime = $bucket + ($index * 60 * 60 * 1000);
            DB::table('egx_candles')->insert([
                'asset_id' => $assetId,
                'timeframe' => '1h',
                'open_time' => $openTime,
                'close_time' => $openTime + (60 * 60 * 1000) - 1,
                'open' => 100 + $index,
                'high' => 102 + $index,
                'low' => 99 + $index,
                'close' => 101 + $index,
                'volume' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
