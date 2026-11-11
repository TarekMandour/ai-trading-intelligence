<?php

namespace Tests\Feature;

use App\Services\MarketData\BinanceSpotClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BinanceSpotClientTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(storage_path('app/cache/binance/exchange_info_symbols.json'));

        parent::tearDown();
    }

    public function test_normalizes_a_valid_order_book(): void
    {
        Http::preventStrayRequests();
        $payload = ['lastUpdateId' => 1, 'bids' => [['99.0', '2']], 'asks' => [['100.0', '3']]];
        Http::fake(['*/api/v3/depth*' => Http::response($payload)]);
        $book = (new BinanceSpotClient)->orderBook('BTCUSDT', 20);
        $this->assertSame(99.0, $book['bids'][0]['price']);
        $this->assertSame(3.0, $book['asks'][0]['quantity']);
    }

    public function test_universe_is_ranked_by_quote_volume_after_spot_usdt_eligibility_filtering(): void
    {
        Http::preventStrayRequests();
        config(['analysis.universe_limit' => 2, 'analysis.exchange_info_min_symbols' => 1]);
        Http::fake([
            '*/api/v3/exchangeInfo' => Http::response(['symbols' => [
                ['symbol' => 'LOWUSDT', 'status' => 'TRADING', 'quoteAsset' => 'USDT', 'isSpotTradingAllowed' => true],
                ['symbol' => 'HIGHUSDT', 'status' => 'TRADING', 'quoteAsset' => 'USDT', 'isSpotTradingAllowed' => true],
                ['symbol' => 'MIDUSDT', 'status' => 'TRADING', 'quoteAsset' => 'USDT', 'isSpotTradingAllowed' => true],
                ['symbol' => 'DISABLEDUSDT', 'status' => 'BREAK', 'quoteAsset' => 'USDT', 'isSpotTradingAllowed' => true],
                ['symbol' => 'NOTSPOTUSDT', 'status' => 'TRADING', 'quoteAsset' => 'USDT', 'isSpotTradingAllowed' => false],
            ]]),
            '*/api/v3/ticker/24hr' => Http::response([
                ['symbol' => 'LOWUSDT', 'quoteVolume' => '10', 'priceChangePercent' => '1', 'lastPrice' => '1'],
                ['symbol' => 'HIGHUSDT', 'quoteVolume' => '900', 'priceChangePercent' => '2', 'lastPrice' => '2'],
                ['symbol' => 'MIDUSDT', 'quoteVolume' => '100', 'priceChangePercent' => '3', 'lastPrice' => '3'],
                ['symbol' => 'DISABLEDUSDT', 'quoteVolume' => '9999'],
                ['symbol' => 'NOTSPOTUSDT', 'quoteVolume' => '9998'],
            ]),
        ]);

        $universe = (new BinanceSpotClient)->eligibleUsdtUniverse();

        $this->assertSame(['HIGHUSDT', 'MIDUSDT'], array_column($universe['symbols'], 'symbol'));
        $this->assertSame([900.0, 100.0], array_column($universe['symbols'], 'quoteVolume'));
    }

    public function test_candles_excludes_the_currently_forming_kline(): void
    {
        Http::preventStrayRequests();
        $now = now()->startOfMinute();
        $this->travelTo($now);
        Http::fake(['*/api/v3/klines*' => Http::response([
            [1000, '1', '2', '0.5', '1.5', '10', ($now->getTimestampMs() - 1), '0', 1, '0', '0', '0'],
            [2000, '1.5', '2.5', '1', '2', '20', ($now->getTimestampMs() + 60000), '0', 1, '0', '0', '0'],
        ])]);

        $candles = (new BinanceSpotClient)->candles('BTCUSDT', '1m');

        $this->assertCount(1, $candles);
        $this->assertSame(1.5, $candles[0]['close']);
    }
}
