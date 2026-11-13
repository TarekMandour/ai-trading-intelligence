<?php

namespace App\Services\Analysis;

/** Finds equal-high/low pools and wick-through/reclaim sweeps of confirmed levels. */
class IctLiquidityAnalyzer
{
    public function analyze(array $candles, array $swings): array
    {
        $tolerance = max(0.0, (float) config('ict.equal_level_tolerance_bps', 8));
        $pools = [];
        foreach (['highs' => 'BSL', 'lows' => 'SSL'] as $key => $side) {
            $points = $swings[$key] ?? [];
            for ($i = 0; $i < count($points); $i++) {
                for ($j = $i + 1; $j < count($points); $j++) {
                    $a = (float) $points[$i]['price'];
                    $b = (float) $points[$j]['price'];
                    $mid = ($a + $b) / 2;
                    if ($mid <= 0 || abs($a - $b) / $mid * 10000 > $tolerance) continue;
                    $pools[] = [
                        'side' => $side, 'level' => $mid, 'touches' => 2,
                        'pivot_indices' => [$points[$i]['index'], $points[$j]['index']],
                        'available_from_index' => max($points[$i]['confirmation_index'], $points[$j]['confirmation_index']) + 1,
                        'status' => 'POTENTIAL_LIQUIDITY',
                    ];
                }
            }
        }

        $sweeps = [];
        foreach ($pools as $pool) {
            for ($i = $pool['available_from_index']; $i < count($candles); $i++) {
                $candle = $candles[$i];
                $level = (float) $pool['level'];
                if ($pool['side'] === 'SSL') {
                    $swept = (float) $candle['low'] < $level && (float) $candle['close'] > $level;
                    $extreme = (float) $candle['low'];
                } else {
                    $swept = (float) $candle['high'] > $level && (float) $candle['close'] < $level;
                    $extreme = (float) $candle['high'];
                }
                if (! $swept) continue;
                $sweeps[] = [
                    'side' => $pool['side'], 'direction_after_reclaim' => $pool['side'] === 'SSL' ? 'BULLISH' : 'BEARISH',
                    'level' => $level, 'extreme' => $extreme, 'index' => $i,
                    'close_time' => (int) ($candle['close_time'] ?? 0),
                    'penetration_bps' => round(abs($level - $extreme) / $level * 10000, 2),
                    'pool_pivot_indices' => $pool['pivot_indices'],
                    'confirmation' => 'WICK_THROUGH_AND_CLOSE_BACK_INSIDE',
                ];
                break;
            }
        }

        usort($sweeps, fn (array $a, array $b): int => $a['index'] <=> $b['index']);
        return [
            'pools' => $pools,
            'sweeps' => $sweeps,
            'latest_bullish_sweep' => $this->latest($sweeps, 'SSL'),
            'latest_bearish_sweep' => $this->latest($sweeps, 'BSL'),
        ];
    }

    private function latest(array $sweeps, string $side): ?array
    {
        $matches = array_values(array_filter($sweeps, fn (array $s): bool => $s['side'] === $side));
        return $matches === [] ? null : $matches[array_key_last($matches)];
    }
}
