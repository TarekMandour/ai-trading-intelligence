<?php

namespace App\\Services\\Analysis;

/** Measures each impulse against ATR and prior-volume baselines (no current-bar leakage). */
class IctDisplacementAnalyzer
{
    public function analyze(array $candles): array
    {
        $period = max(5, (int) config('ict.atr_period', 14));
        $volumePeriod = max(5, (int) config('ict.volume_period', 20));
        $events = [];
        for ($i = max($period, $volumePeriod); $i < count($candles); $i++) {
            $atr = $this->atr($candles, $i, $period);
            $averageVolume = $this->averageVolume($candles, $i, $volumePeriod);
            $c = $candles[$i];
            $range = (float) $c['high'] - (float) $c['low'];
            $body = abs((float) $c['close'] - (float) $c['open']);
            $bodyRatio = $range > 0 ? $body / $range : 0.0;
            $volumeRatio = $averageVolume > 0 ? (float) $c['volume'] / $averageVolume : 0.0;
            $rangeAtr = $atr > 0 ? $range / $atr : 0.0;
            $direction = (float) $c['close'] > (float) $c['open'] ? 'BULLISH' : ((float) $c['close'] < (float) $c['open'] ? 'BEARISH' : 'NEUTRAL');
            $valid = $direction !== 'NEUTRAL'
                && $rangeAtr >= (float) config('ict.displacement_atr_multiple', 1.2)
                && $bodyRatio >= (float) config('ict.displacement_body_ratio', .65)
                && $volumeRatio >= (float) config('ict.displacement_volume_multiple', 1.0);
            if (! $valid) continue;
            $closeLocation = $direction === 'BULLISH'
                ? ($range > 0 ? ((float) $c['close'] - (float) $c['low']) / $range : 0)
                : ($range > 0 ? ((float) $c['high'] - (float) $c['close']) / $range : 0);
            $events[] = [
                'index' => $i, 'direction' => $direction,
                'open_time' => (int) ($c['open_time'] ?? 0), 'close_time' => (int) ($c['close_time'] ?? 0),
                'atr' => $atr, 'range' => $range, 'range_atr' => round($rangeAtr, 3),
                'body_ratio' => round($bodyRatio, 4), 'volume' => (float) $c['volume'],
                'average_prior_volume' => $averageVolume, 'volume_ratio' => round($volumeRatio, 3),
                'close_location' => round($closeLocation, 4),
                'quality' => min(100, (int) round(min(2.5, $rangeAtr) / 2.5 * 35 + min(1, $bodyRatio) * 30 + min(2.5, $volumeRatio) / 2.5 * 25 + $closeLocation * 10)),
            ];
        }
        return ['events' => $events, 'latest_bullish' => $this->latest($events, 'BULLISH'), 'latest_bearish' => $this->latest($events, 'BEARISH')];
    }

    private function atr(array $candles, int $index, int $period): float
    {
        $sum = 0.0;
        for ($i = $index - $period; $i < $index; $i++) {
            $previousClose = (float) $candles[max(0, $i - 1)]['close'];
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $sum += max($high - $low, abs($high - $previousClose), abs($low - $previousClose));
        }
        return $sum / $period;
    }

    private function averageVolume(array $candles, int $index, int $period): float
    {
        $values = array_map(fn (array $c): float => (float) $c['volume'], array_slice($candles, $index - $period, $period));
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private function latest(array $events, string $direction): ?array
    {
        $matches = array_values(array_filter($events, fn (array $e): bool => $e['direction'] === $direction));
        return $matches === [] ? null : $matches[array_key_last($matches)];
    }
}
