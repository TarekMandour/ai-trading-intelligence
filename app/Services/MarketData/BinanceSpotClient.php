<?php

namespace App\Services\MarketData;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BinanceSpotClient
{
    /** @return array{symbols: array<int, array<string, mixed>>, tickers: array<int, array<string, mixed>>} */
    public function eligibleUsdtUniverse(): array
    {
        try {
            $baseUrl = config('analysis.binance_base_url');
            $responses = Http::pool(fn ($pool) => [
                'exchange' => $pool->baseUrl($baseUrl)->connectTimeout(3)->timeout(config('analysis.timeout_seconds'))->get('/api/v3/exchangeInfo'),
                'tickers' => $pool->baseUrl($baseUrl)->connectTimeout(3)->timeout(config('analysis.timeout_seconds'))->get('/api/v3/ticker/24hr'),
            ]);
            $symbols = $responses['exchange']->throw()->json('symbols');
            $tickers = collect($responses['tickers']->throw()->json())
                ->filter(fn (array $ticker): bool => is_numeric($ticker['quoteVolume'] ?? null))
                ->sortByDesc(fn (array $ticker): float => (float) $ticker['quoteVolume'])
                ->values();
            $eligible = collect($symbols)->filter(fn (array $symbol): bool => ($symbol['status'] ?? null) === 'TRADING' && ($symbol['quoteAsset'] ?? null) === 'USDT' && ($symbol['isSpotTradingAllowed'] ?? false));

            return ['symbols' => $eligible->take(config('analysis.universe_limit'))->values()->all(), 'tickers' => $tickers->all()];
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('Binance public REST connection failed.', previous: $exception);
        }
    }

    /** @return array<int, array{open_time:int,close_time:int,open:float,high:float,low:float,close:float,volume:float}> */
    public function candles(string $symbol, string $interval, int $limit = 100): array
    {
        $rows = Http::baseUrl(config('analysis.binance_base_url'))->connectTimeout(3)->timeout(config('analysis.timeout_seconds'))->retry([100, 300])->get('/api/v3/klines', compact('symbol', 'interval', 'limit'))->throw()->json();

        return collect($rows)->map(fn (array $r): array => ['open_time' => (int) $r[0], 'open' => (float) $r[1], 'high' => (float) $r[2], 'low' => (float) $r[3], 'close' => (float) $r[4], 'volume' => (float) $r[5], 'close_time' => (int) $r[6]])->values()->all();
    }

    /** @return array{bids: array<int,array{price:float,quantity:float}>,asks: array<int,array{price:float,quantity:float}>,last_update_id:int} */
    public function orderBook(string $symbol, ?int $limit = null): array
    {
        $limit ??= config('analysis.order_book_limit');
        if (! in_array($limit, [5, 10, 20, 50, 100, 500, 1000, 5000], true)) {
            throw new \InvalidArgumentException('Invalid Binance order-book limit.');
        }
        try {
            $data = Http::baseUrl(config('analysis.binance_base_url'))->connectTimeout(3)->timeout(config('analysis.timeout_seconds'))->retry([100, 300])->get('/api/v3/depth', compact('symbol', 'limit'))->throw()->json();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Binance order-book request failed.', previous: $exception);
        }
        $normalize = function (mixed $levels): array {
            if (! is_array($levels) || $levels === []) {
                throw new \RuntimeException('Binance order-book response is unavailable.');
            }

            return collect($levels)->map(function (mixed $level): array {
                if (! is_array($level) || ! isset($level[0], $level[1]) || ! is_numeric($level[0]) || ! is_numeric($level[1]) || (float) $level[0] <= 0 || (float) $level[1] <= 0) {
                    throw new \RuntimeException('Binance order-book response is invalid.');
                }

                return ['price' => (float) $level[0], 'quantity' => (float) $level[1]];
            })->all();
        };
        $bids = $normalize($data['bids'] ?? null);
        $asks = $normalize($data['asks'] ?? null);
        if ($bids[0]['price'] >= $asks[0]['price']) {
            throw new \RuntimeException('Binance order-book spread is invalid.');
        }

        return ['bids' => $bids, 'asks' => $asks, 'last_update_id' => (int) ($data['lastUpdateId'] ?? 0)];
    }
}
