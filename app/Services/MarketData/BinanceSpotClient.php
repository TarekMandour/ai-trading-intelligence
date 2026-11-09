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
            // Use sequential requests here. The pooled responses were returning a valid
            // ticker payload but an empty eligible universe in the local runtime, despite
            // both endpoints returning valid data when called independently.
            $exchangeResponse = Http::baseUrl($baseUrl)
                ->connectTimeout(5)
                ->timeout(config('analysis.timeout_seconds'))
                ->retry([250, 750, 1500])
                ->get('/api/v3/exchangeInfo')
                ->throw();

            $tickerResponse = Http::baseUrl($baseUrl)
                ->connectTimeout(5)
                ->timeout(config('analysis.timeout_seconds'))
                ->retry([250, 750, 1500])
                ->get('/api/v3/ticker/24hr')
                ->throw();

            $exchangeSymbols = $exchangeResponse->json('symbols', []);
            $tickers = collect($tickerResponse->json())
                ->filter(fn (array $ticker): bool => is_string($ticker['symbol'] ?? null) && is_numeric($ticker['quoteVolume'] ?? null))
                ->keyBy('symbol');

            // Rank only symbols that are actually eligible for Binance Spot USDT trading.
            // Ranking the entire ticker list before filtering exchangeInfo can otherwise
            // exclude liquid eligible pairs and select arbitrary symbols from exchangeInfo.
            $eligible = collect($exchangeSymbols)
                ->filter(fn (array $symbol): bool => ($symbol['status'] ?? null) === 'TRADING'
                    && ($symbol['quoteAsset'] ?? null) === 'USDT'
                    && ($symbol['isSpotTradingAllowed'] ?? false) === true
                    && isset($tickers[$symbol['symbol']]))
                ->map(function (array $symbol) use ($tickers): array {
                    $ticker = $tickers[$symbol['symbol']];
                    $symbol['quoteVolume'] = (float) $ticker['quoteVolume'];
                    $symbol['priceChangePercent24h'] = is_numeric($ticker['priceChangePercent'] ?? null)
                        ? (float) $ticker['priceChangePercent']
                        : null;
                    $symbol['lastPrice'] = is_numeric($ticker['lastPrice'] ?? null)
                        ? (float) $ticker['lastPrice']
                        : null;

                    return $symbol;
                })
                ->sortByDesc('quoteVolume')
                ->values();

            return [
                'symbols' => $eligible->take(max(1, (int) config('analysis.universe_limit')))->all(),
                'tickers' => $tickers->values()->all(),
            ];
        } catch (ConnectionException $exception) {
            report($exception);
            throw new \\RuntimeException(
                'Binance public REST connection failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /** @return array<int, array{open_time:int,close_time:int,open:float,high:float,low:float,close:float,volume:float}> */
    public function candles(string $symbol, string $interval, int $limit = 100): array
    {
        $rows = Http::baseUrl(config('analysis.binance_base_url'))->connectTimeout(3)->timeout(config('analysis.timeout_seconds'))->retry([100, 300])->get('/api/v3/klines', compact('symbol', 'interval', 'limit'))->throw()->json();
        $nowMs = now()->getTimestampMs();

        // Binance includes the currently-forming candle in klines. Exclude it so every
        // downstream indicator and decision is based only on completed candles.
        return collect($rows)
            ->filter(fn (array $row): bool => isset($row[6]) && is_numeric($row[6]) && (int) $row[6] <= $nowMs)
            ->map(fn (array $r): array => [
                'open_time' => (int) $r[0],
                'open' => (float) $r[1],
                'high' => (float) $r[2],
                'low' => (float) $r[3],
                'close' => (float) $r[4],
                'volume' => (float) $r[5],
                'close_time' => (int) $r[6],
            ])
            ->values()
            ->all();
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
