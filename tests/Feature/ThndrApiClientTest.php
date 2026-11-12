<?php

namespace Tests\Feature;

use App\Models\MarketSetting;
use App\Services\MarketData\ThndrApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThndrApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketwatch_uses_saved_token_and_egypt_market(): void
    {
        Http::preventStrayRequests();
        MarketSetting::create(['key' => 'thndr_api_token', 'value' => 'test-token']);
        Http::fake(['*/assets-service/assets/marketwatch*' => Http::response(['assets' => [['asset_id' => 'asset-1']]])]);

        $payload = app(ThndrApiClient::class)->marketWatch();

        $this->assertSame('asset-1', $payload['assets'][0]['asset_id']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token')
            && str_contains($request->url(), 'market=egypt'));
    }

    public function test_candle_timestamps_include_timeframe_duration(): void
    {
        Http::preventStrayRequests();
        MarketSetting::create(['key' => 'thndr_api_token', 'value' => 'test-token']);
        Http::fake(['*/advanced-charts/v2/asset-1/trades*' => Http::response([
            'trades_candles' => [['timestamp' => '2026-11-10T09:00:00Z', 'open' => 10, 'high' => 12, 'low' => 9, 'close' => 11, 'volume' => 100]],
        ])]);

        $candles = app(ThndrApiClient::class)->candles('asset-1', '1h', 0, now()->timestamp);

        $this->assertCount(1, $candles);
        $this->assertSame(strtotime('2026-11-10T09:00:00Z') * 1000, $candles[0]['open_time']);
        $this->assertSame((strtotime('2026-11-10T10:00:00Z') * 1000) - 1, $candles[0]['close_time']);
    }

    public function test_missing_token_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('THNDR_TOKEN_NOT_CONFIGURED');
        app(ThndrApiClient::class)->marketWatch();
    }
}
