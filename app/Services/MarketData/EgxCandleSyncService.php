<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class EgxCandleSyncService
{
    /** Download only missing tail data; refresh recent bars to replace provisional values. */
    public function sync(string $assetId, string $timeframe, ThndrApiClient $api): array
    {
        $last = DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', $timeframe)->max('open_time');
        $start = $last
            ? max(0, (int) ($last / 1000) - $this->refreshSeconds($timeframe))
            : now()->subDays((int) config('thndr.candle_history_days', 365))->timestamp;
        $end = now()->timestamp;
        $rows = $api->candles($assetId, $timeframe, $start, $end);
        $rows = array_values(array_filter($rows, fn (array $row) => $row['close_time'] <= now()->getTimestampMs()));
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('egx_candles')->upsert(array_map(fn (array $c) => $c + [
                'asset_id' => $assetId, 'timeframe' => $timeframe, 'created_at' => now(), 'updated_at' => now(),
            ], $chunk), ['asset_id', 'timeframe', 'open_time'], ['open','high','low','close','volume','close_time','updated_at']);
        }
        return ['asset_id' => $assetId, 'timeframe' => $timeframe, 'fetched' => count($rows), 'last_open_time' => $rows === [] ? $last : end($rows)['open_time']];
    }

    private function refreshSeconds(string $timeframe): int
    {
        return match ($timeframe) { '1m' => 120, '5m' => 600, '15m' => 1800, '1h' => 7200, '4h' => 14400, '1d' => 172800, '1w' => 1209600, default => 3600 };
    }

    public function candles(string $assetId, string $timeframe, int $limit = 500): array
    {
        $rows = DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', $timeframe)->orderByDesc('open_time')->limit($limit)->get()->reverse()->values();
        return $rows->map(fn ($r) => (array) $r)->all();
    }
}
