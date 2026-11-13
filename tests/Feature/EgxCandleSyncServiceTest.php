<?php

namespace Tests\Feature;

use App\Models\MarketSetting;
use App\Services\MarketData\EgxCandleSyncService;
use App\Services\MarketData\ThndrApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EgxCandleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MarketSetting::create(['key' => 'thndr_api_token', 'value' => 'test-token']);
        Http::preventStrayRequests();
    }

    public function test_sync_persists_candles_and_uses_a_small_overlap_on_next_sync(): void
    {
        $stamp = strtotime('2026-11-10T09:00:00Z');
        Http::fake(['*/advanced-charts/v2/asset-1/trades*' => Http::sequence()
            ->push(['trades_candles' => [[
                'timestamp' => '2026-11-10T09:00:00Z', 'open' => 10, 'high' => 12, 'low' => 9, 'close' => 11, 'volume' => 100,
            ]]])
            ->push(['trades_candles' => []])]);

        $sync = app(EgxCandleSyncService::class);
        $api = app(ThndrApiClient::class);
        $sync->sync('asset-1', '1h', $api);
        $sync->sync('asset-1', '1h', $api);

        $this->assertDatabaseCount('egx_candles', 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'start_timestamp='.(string) ($stamp - 7200)));
    }

    public function test_fifteen_minute_candles_are_aggregated_from_three_five_minute_candles(): void
    {
        Http::fake(['*/advanced-charts/v2/asset-1/trades*' => Http::response(['trades_candles' => [
            ['timestamp' => '2026-11-10T09:00:00Z', 'open' => 10, 'high' => 11, 'low' => 9, 'close' => 10.5, 'volume' => 100],
            ['timestamp' => '2026-11-10T09:05:00Z', 'open' => 10.5, 'high' => 12, 'low' => 10, 'close' => 11.5, 'volume' => 150],
            ['timestamp' => '2026-11-10T09:10:00Z', 'open' => 11.5, 'high' => 13, 'low' => 11, 'close' => 12.5, 'volume' => 200],
        ]])]);

        $sync = app(EgxCandleSyncService::class);
        $sync->sync('asset-1', '15m', app(ThndrApiClient::class));
        $candles = $sync->candles('asset-1', '15m');

        $this->assertCount(1, $candles);
        $this->assertSame(10.0, (float) $candles[0]['open']);
        $this->assertSame(13.0, (float) $candles[0]['high']);
        $this->assertSame(9.0, (float) $candles[0]['low']);
        $this->assertSame(12.5, (float) $candles[0]['close']);
        $this->assertSame(450.0, (float) $candles[0]['volume']);
    }
}
