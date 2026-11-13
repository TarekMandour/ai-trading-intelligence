<?php

namespace App\Services\Analysis;

/**
 * Causal pivot detector. A pivot is exposed only at its confirmation index;
 * callers must never treat its origin index as the time it became knowable.
 */
class IctSwingPointDetector
{
    public function detect(array $candles): array
    {
        $left = max(1, (int) config('ict.swing_left', 2));
        $right = max(1, (int) config('ict.swing_right', 2));
        $highs = $lows = [];
        $count = count($candles);

        for ($i = $left; $i + $right < $count; $i++) {
            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $isHigh = true;
            $isLow = true;

            for ($j = $i - $left; $j <= $i + $right; $j++) {
                if ($j === $i) continue;
                if ((float) $candles[$j]['high'] >= $high) $isHigh = false;
                if ((float) $candles[$j]['low'] <= $low) $isLow = false;
                if (! $isHigh && ! $isLow) break;
            }

            if ($isHigh) $highs[] = $this->point($candles, $i, 'HIGH', $right);
            if ($isLow) $lows[] = $this->point($candles, $i, 'LOW', $right);
        }

        return [
            'highs' => $highs,
            'lows' => $lows,
            'method' => 'STRICT_FRACTAL_CONFIRMED_AFTER_RIGHT_BARS',
            'right_bars' => $right,
        ];
    }

    private function point(array $candles, int $index, string $type, int $right): array
    {
        return [
            'type' => $type,
            'index' => $index,
            'confirmation_index' => $index + $right,
            'price' => (float) $candles[$index][$type === 'HIGH' ? 'high' : 'low'],
            'origin_close_time' => (int) ($candles[$index]['close_time'] ?? 0),
            'confirmed_at' => (int) ($candles[$index + $right]['close_time'] ?? 0),
        ];
    }
}
