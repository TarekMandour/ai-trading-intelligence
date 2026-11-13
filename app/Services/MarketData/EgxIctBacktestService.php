<?php

namespace App\Services\MarketData;

use App\Services\Analysis\IctHistoricalBacktestService;

/** Replays persisted Thndr/EGX candles through the same deterministic ICT engine. */
class EgxIctBacktestService
{
    private const PROFILE_FRAMES = [
        'Scalping' => ['context' => '1h', 'structure' => '15m', 'setup' => '5m', 'entry' => '1m'],
        'Intraday' => ['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'],
        'Swing' => ['context' => '1w', 'structure' => '1d', 'setup' => '4h', 'entry' => '1h'],
    ];

    public function __construct(
        private readonly EgxCandleSyncService $candles,
        private readonly IctHistoricalBacktestService $backtest,
    ) {}

    public function run(string $assetId, string $profile, array $assumptions = []): array
    {
        if (! isset(self::PROFILE_FRAMES[$profile])) {
            return ['status' => 'REJECTED', 'market' => 'EGX', 'reason' => 'UNSUPPORTED_PROFILE'];
        }

        $series = [];
        $counts = [];
        foreach (self::PROFILE_FRAMES[$profile] as $role => $timeframe) {
            $rows = $this->candles->candles($assetId, $timeframe, (int) ($assumptions['candle_limit'] ?? 5000));
            $counts[$timeframe] = count($rows);
            if (count($rows) < max(30, (int) config('ict.minimum_candles', 30))) {
                return [
                    'status' => 'REJECTED', 'market' => 'EGX', 'asset_id' => $assetId,
                    'profile' => $profile, 'reason' => 'INSUFFICIENT_HISTORICAL_CANDLES_'.$timeframe,
                    'candle_counts' => $counts,
                ];
            }
            $series[$role] = array_map(fn (array $c): array => [
                'open_time' => (int) $c['open_time'], 'close_time' => (int) $c['close_time'],
                'open' => (float) $c['open'], 'high' => (float) $c['high'],
                'low' => (float) $c['low'], 'close' => (float) $c['close'],
                'volume' => (float) $c['volume'],
            ], $rows);
        }

        $result = $this->backtest->runChronologicalSplits($series, $profile, $assumptions);
        return array_merge($result, [
            'status' => 'COMPLETED',
            'market' => 'EGX',
            'venue' => 'THNDR_HISTORICAL_CANDLES',
            'asset_id' => $assetId,
            'timeframes' => self::PROFILE_FRAMES[$profile],
            'candle_counts' => $counts,
            'corporate_action_adjustment' => 'NOT_AVAILABLE_IN_CURRENT_DATASET',
            'exchange_session_calendar' => 'THNDR_CANDLE_TIMESTAMPS_USED_NO_ADDITIONAL_SESSION_RECONSTRUCTION',
        ]);
    }
}
