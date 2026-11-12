<?php

namespace App\Services\Analysis;

use Carbon\CarbonImmutable;

/** Produces research-only Market and Limit plans. It never submits exchange orders. */
class IctEntryPlanner
{
    public function plan(float $referencePrice, ?array $zone, bool $triggerConfirmed, ?float $invalidationPrice = null): array
    {
        if ($referencePrice <= 0) {
            return ['market' => ['status' => 'REJECTED', 'reason' => 'INVALID_REFERENCE_PRICE'], 'limit' => ['status' => 'REJECTED', 'reason' => 'INVALID_REFERENCE_PRICE']];
        }

        $maxDistanceBps = max(0.0, (float) config('ict.max_market_distance_bps', 35));
        $expiryMinutes = max(1, (int) config('ict.entry_expiry_minutes', 30));
        $validUntil = CarbonImmutable::now()->addMinutes($expiryMinutes)->toIso8601String();
        $marketDistance = $zone === null ? 0.0 : abs($referencePrice - (($zone['low'] + $zone['high']) / 2)) / $referencePrice * 10000;
        $marketAllowed = $triggerConfirmed && $marketDistance <= $maxDistanceBps;

        $market = [
            'entry_type' => 'MARKET',
            'status' => $marketAllowed ? 'PLANNED' : 'WAIT',
            'reference_price' => $referencePrice,
            'max_distance_bps' => $maxDistanceBps,
            'distance_from_zone_bps' => $marketDistance,
            'valid_until' => $validUntil,
            'trigger_confirmed' => $triggerConfirmed,
            'cancel_condition' => 'MODEL_INVALIDATED_OR_EXPIRY',
            'execution_price_source' => 'LIVE_ASK_REQUIRED',
        ];

        $limitPrice = $zone === null ? null : ($zone['ce'] ?? (($zone['low'] + $zone['high']) / 2));
        $limit = [
            'entry_type' => 'LIMIT',
            'status' => $limitPrice !== null && $limitPrice > 0 ? 'PLANNED' : 'UNAVAILABLE',
            'limit_price' => $limitPrice,
            'zone_low' => $zone['low'] ?? null,
            'zone_high' => $zone['high'] ?? null,
            'valid_until' => $validUntil,
            'cancel_condition' => 'MODEL_INVALIDATED_OR_EXPIRY',
            'fill_policy' => 'TOUCH_IS_NOT_A_FILL',
            'invalidation_price' => $invalidationPrice,
        ];

        return ['market' => $market, 'limit' => $limit, 'order_submission_enabled' => false];
    }
}
