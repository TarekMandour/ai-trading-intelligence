<?php

namespace App\Services\Analysis;

/** Tracks post-creation zone interactions without treating a touch as an order fill. */
class IctZoneLifecycleAnalyzer
{
    public function bullishFvg(array $zone, array $candles): array
    {
        $createdAt = (int) ($zone['created_at'] ?? 0);
        $status = 'ACTIVE';
        $touches = 0;
        $deepestPrice = null;
        $invalidationClose = null;

        foreach ($candles as $candle) {
            if ((int) ($candle['close_time'] ?? 0) <= $createdAt) continue;
            $low = (float) $candle['low'];
            if (in_array($status, ['FULLY_FILLED', 'INVALIDATED'], true)) continue;

            if ($low <= (float) $zone['high']) {
                $touches++;
                $deepestPrice = $deepestPrice === null ? $low : min($deepestPrice, $low);
                $status = 'PARTIALLY_FILLED';
            }
            if ($low <= (float) $zone['low']) $status = 'FULLY_FILLED';
            if ((float) $candle['close'] < (float) $zone['low']) {
                $status = 'INVALIDATED';
                $invalidationClose = (float) $candle['close'];
            }
        }

        return array_merge($zone, [
            'status' => $status,
            'touch_count' => $touches,
            'deepest_price' => $deepestPrice,
            'invalidation_close' => $invalidationClose,
            'touch_is_fill' => false,
        ]);
    }

    public function bullishOrderBlock(array $zone, array $candles): array
    {
        $createdAt = (int) ($zone['created_at'] ?? 0);
        $status = 'ACTIVE';
        $touches = 0;
        $invalidationClose = null;

        foreach ($candles as $candle) {
            if ((int) ($candle['close_time'] ?? 0) <= $createdAt) continue;
            if ($status === 'INVALIDATED') continue;

            if ((float) $candle['low'] <= (float) $zone['high'] && (float) $candle['high'] >= (float) $zone['low']) {
                $touches++;
                $status = 'MITIGATED';
            }
            if ((float) $candle['close'] < (float) $zone['low']) {
                $status = 'INVALIDATED';
                $invalidationClose = (float) $candle['close'];
            }
        }

        return array_merge($zone, ['status' => $status, 'touch_count' => $touches, 'invalidation_close' => $invalidationClose, 'touch_is_fill' => false]);
    }
}
