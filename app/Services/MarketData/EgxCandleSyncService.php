<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class EgxCandleSyncService
{
    /** Incremental sync. Derived frames are built from their native lower timeframe. */
    public function sync(string $assetId, string $timeframe, ThndrApiClient $api): array
    {
        if (in_array($timeframe, ['15m', '4h'], true)) {
            $base = $timeframe === '15m' ? '5m' : '1h';
            $baseResult = $this->sync($assetId, $base, $api);
            $this->aggregate($assetId, $base, $timeframe);
            return ['asset_id' => $assetId, 'timeframe' => $timeframe, 'derived_from' => $base, 'fetched' => $baseResult['fetched']];
        }

        $last = DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', $timeframe)->max('open_time');
        $start = $last
            ? max(0, (int) ($last / 1000) - $this->refreshSeconds($timeframe))
            : now()->subDays((int) config('thndr.candle_history_days', 365))->timestamp;
        $rows = $api->candles($assetId, $timeframe, $start, now()->timestamp);
        $rows = array_values(array_filter($rows, fn (array $row) => $row['close_time'] <= now()->getTimestampMs()));
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('egx_candles')->upsert(array_map(fn (array $c) => $c + [
                'asset_id' => $assetId, 'timeframe' => $timeframe, 'created_at' => now(), 'updated_at' => now(),
            ], $chunk), ['asset_id', 'timeframe', 'open_time'], ['open','high','low','close','volume','close_time','updated_at']);
        }
        return ['asset_id' => $assetId, 'timeframe' => $timeframe, 'fetched' => count($rows), 'last_open_time' => $rows === [] ? $last : end($rows)['open_time']];
    }

    private function aggregate(string $assetId, string $source, string $target): void
    {
        $minutes = $target === '15m' ? 15 : 240;
        $rows = DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', $source)->orderBy('open_time')->get();
        $groups = $rows->groupBy(fn ($c) => (int) (floor(($c->open_time / 1000) / ($minutes * 60)) * ($minutes * 60)));
        $aggregated = [];
        $sourceIntervalMs = $source === '5m' ? 5 * 60 * 1000 : 60 * 60 * 1000;
        $expectedSourceBars = $minutes * 60 * 1000 / $sourceIntervalMs;
        foreach ($groups as $bucket => $items) {
            $items = $items->sortBy('open_time')->values();
            if ($items->count() !== $expectedSourceBars) continue;

            $expectedOpenTimes = [];
            for ($i = 0; $i < $expectedSourceBars; $i++) {
                $expectedOpenTimes[] = ((int) $bucket * 1000) + ($i * $sourceIntervalMs);
            }
            if ($items->pluck('open_time')->map(fn ($time) => (int) $time)->all() !== $expectedOpenTimes) continue;

            $closeTime = ((int) $bucket * 1000) + ($minutes * 60 * 1000) - 1;
            if ($closeTime > now()->getTimestampMs()) continue;

            $aggregated[] = [
                'asset_id' => $assetId, 'timeframe' => $target, 'open_time' => (int) $bucket * 1000,
                'close_time' => $closeTime,
                'open' => $items->first()->open, 'high' => $items->max('high'), 'low' => $items->min('low'),
                'close' => $items->last()->close, 'volume' => $items->sum('volume'),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($aggregated, 500) as $chunk) DB::table('egx_candles')->upsert(
            $chunk, ['asset_id','timeframe','open_time'], ['close_time','open','high','low','close','volume','updated_at']
        );
    }

    private function refreshSeconds(string $timeframe): int
    {
        return match ($timeframe) { '1m' => 120, '5m' => 600, '10m' => 1200, '1h' => 7200, '1d' => 172800, '1w' => 1209600, default => 3600 };
    }

    public function candles(string $assetId, string $timeframe, int $limit = 500): array
    {
        return DB::table('egx_candles')->where('asset_id', $assetId)->where('timeframe', $timeframe)
            ->orderByDesc('open_time')->limit($limit)->get()->reverse()->values()
            ->map(fn ($r) => (array) $r)->all();
    }
}
