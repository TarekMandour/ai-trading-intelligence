<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ThndrApiClient
{
    public function token(): string
    {
        $token = (string) (\App\Models\MarketSetting::query()->where('key', 'thndr_api_token')->value('value') ?? '');
        if ($token === '') throw new RuntimeException('THNDR_TOKEN_NOT_CONFIGURED');
        return $token;
    }

    private function request(string $path, array $query = []): array
    {
        $response = Http::baseUrl(rtrim(config('thndr.base_url'), '/'))
            ->acceptJson()->withUserAgent(config('thndr.user_agent'))
            ->withToken($this->token())->connectTimeout(5)
            ->timeout((int) config('thndr.timeout_seconds', 20))
            ->retry([250, 750], throw: false)->get($path, $query);

        if (!$response->successful()) {
            Log::warning('Thndr API request failed', ['path' => $path, 'status' => $response->status()]);
            throw new RuntimeException('THNDR_HTTP_'.$response->status());
        }
        $json = $response->json();
        if (!is_array($json)) throw new RuntimeException('THNDR_INVALID_JSON');
        return $json;
    }

    public function marketWatch(): array
    {
        return $this->request('/assets-service/assets/marketwatch', ['market' => config('thndr.market', 'egypt')]);
    }

    public function candles(string $assetId, string $timeframe, int $start, int $end): array
    {
        $resolution = config('thndr.resolutions.'.$timeframe);
        if (!$resolution) throw new RuntimeException('THNDR_UNSUPPORTED_TIMEFRAME_'.$timeframe);
        $data = $this->request('/krakend-thndr-x/feed/advanced-charts/v2/'.rawurlencode($assetId).'/trades', [
            'resolution' => $resolution, 'start_timestamp' => $start, 'end_timestamp' => $end,
        ]);
        $rows = $data['trades_candles'] ?? null;
        if (!is_array($rows)) throw new RuntimeException('THNDR_CANDLES_PAYLOAD_INVALID');
        return collect($rows)->map(function (array $row): array {
            $raw = $row['timestamp'] ?? 0;
            $time = is_numeric($raw) ? (int) $raw : (strtotime((string) $raw) ?: 0);
            if ($time > 100000000000) $time = intdiv($time, 1000);
            return [
                'open_time' => $time * 1000,
                'open' => (float) ($row['open'] ?? 0), 'high' => (float) ($row['high'] ?? 0),
                'low' => (float) ($row['low'] ?? 0), 'close' => (float) ($row['close'] ?? 0),
                'volume' => (float) ($row['volume'] ?? 0), 'close_time' => $time * 1000,
            ];
        })->filter(fn (array $c) => $c['open_time'] > 0 && $c['high'] >= max($c['open'], $c['close']) && $c['low'] <= min($c['open'], $c['close']) && $c['high'] >= $c['low'])->sortBy('open_time')->values()->all();
    }
}
