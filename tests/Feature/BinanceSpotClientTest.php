<?php

namespace Tests\Feature;

use App\Services\MarketData\BinanceSpotClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BinanceSpotClientTest extends TestCase
{
    public function test_normalizes_a_valid_order_book(): void
    {
        Http::preventStrayRequests();
        $payload = ['lastUpdateId' => 1, 'bids' => [['99.0', '2']], 'asks' => [['100.0', '3']]];
        Http::fake(['*/api/v3/depth*' => Http::response($payload)]);
        $book = (new BinanceSpotClient)->orderBook('BTCUSDT', 20);
        $this->assertSame(99.0, $book['bids'][0]['price']);
        $this->assertSame(3.0, $book['asks'][0]['quantity']);
    }
}
