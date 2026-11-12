<?php

namespace App\Services\Analysis;

/**
 * Detects an ordered bullish ICT event chain on completed candles.
 * Pivot levels are usable only after their right-side confirmation candles.
 */
class IctBullishSequenceAnalyzer
{
    public function detect(array $candles): array
    {
        $left = max(1, (int) config('ict.swing_left', 2));
        $right = max(1, (int) config('ict.swing_right', 2));
        $swings = $this->swings($candles, $left, $right);
        $count = count($candles);

        for ($sweepIndex = $count - 4; $sweepIndex >= max(14, $left + $right + 1); $sweepIndex--) {
            $ssl = $this->latestConfirmed($swings['lows'], $sweepIndex);
            if ($ssl === null) continue;

            $sweep = $candles[$sweepIndex];
            if ((float) $sweep['low'] >= $ssl['price'] || (float) $sweep['close'] <= $ssl['price']) continue;

            for ($displacementIndex = $sweepIndex + 1; $displacementIndex < $count - 2; $displacementIndex++) {
                $displacement = $candles[$displacementIndex];
                $atr = $this->priorAtr($candles, $displacementIndex, 14);
                $range = (float) $displacement['high'] - (float) $displacement['low'];
                $body = (float) $displacement['close'] - (float) $displacement['open'];
                $bodyRatio = $range > 0 ? $body / $range : 0.0;
                $isDisplacement = $atr > 0
                    && $body > 0
                    && $range >= $atr * (float) config('ict.displacement_atr_multiple', 1.2)
                    && $bodyRatio >= (float) config('ict.displacement_body_ratio', 0.65);
                if (! $isDisplacement) continue;

                $priorHigh = $this->latestConfirmed($swings['highs'], $displacementIndex);
                if ($priorHigh === null || (float) $displacement['close'] <= $priorHigh['price']) continue;

                for ($fvgIndex = $displacementIndex; $fvgIndex < $count; $fvgIndex++) {
                    if ($fvgIndex < 2) continue;
                    $first = $candles[$fvgIndex - 2];
                    $third = $candles[$fvgIndex];
                    $zoneLow = (float) $first['high'];
                    $zoneHigh = (float) $third['low'];
                    if ($zoneHigh <= $zoneLow) continue;

                    return [
                        'status' => 'CONFIRMED',
                        'sweep' => ['index' => $sweepIndex, 'level' => $ssl['price'], 'low' => (float) $sweep['low'], 'close_time' => (int) $sweep['close_time']],
                        'displacement' => ['index' => $displacementIndex, 'atr' => $atr, 'body_ratio' => $bodyRatio, 'close_time' => (int) $displacement['close_time']],
                        'mss' => ['level' => $priorHigh['price'], 'index' => $displacementIndex, 'close_time' => (int) $displacement['close_time']],
                        'fvg' => ['low' => $zoneLow, 'high' => $zoneHigh, 'ce' => ($zoneLow + $zoneHigh) / 2, 'created_at' => (int) $third['close_time'], 'source_indices' => [$fvgIndex - 2, $fvgIndex - 1, $fvgIndex]],
                    ];
                }
            }
        }

        return ['status' => 'NOT_CONFIRMED', 'sweep' => null, 'displacement' => null, 'mss' => null, 'fvg' => null];
    }

    private function swings(array $candles, int $left, int $right): array
    {
        $highs = $lows = [];
        for ($i = $left; $i < count($candles) - $right; $i++) {
            $window = array_slice($candles, $i - $left, $left + $right + 1);
            $others = array_merge(array_slice($window, 0, $left), array_slice($window, $left + 1));
            if ((float) $candles[$i]['high'] > max(array_column($others, 'high'))) {
                $highs[] = ['index' => $i, 'confirmation_index' => $i + $right, 'price' => (float) $candles[$i]['high']];
            }
            if ((float) $candles[$i]['low'] < min(array_column($others, 'low'))) {
                $lows[] = ['index' => $i, 'confirmation_index' => $i + $right, 'price' => (float) $candles[$i]['low']];
            }
        }
        return compact('highs', 'lows');
    }

    private function latestConfirmed(array $swings, int $beforeIndex): ?array
    {
        $confirmed = array_values(array_filter($swings, fn (array $swing): bool => $swing['confirmation_index'] < $beforeIndex));
        return $confirmed === [] ? null : $confirmed[array_key_last($confirmed)];
    }

    private function priorAtr(array $candles, int $index, int $period): float
    {
        if ($index < $period) return 0.0;
        $ranges = [];
        for ($i = $index - $period; $i < $index; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $previousClose = $i > 0 ? (float) $candles[$i - 1]['close'] : (float) $candles[$i]['close'];
            $ranges[] = max(
                $high - $low,
                abs($high - $previousClose),
                abs($low - $previousClose),
            );
        }
        return array_sum($ranges) / $period;
    }
}
