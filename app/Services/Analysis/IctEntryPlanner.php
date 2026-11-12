<?php

namespace App\Services\Analysis;

use Carbon\CarbonImmutable;

/** Produces research-only Market and Limit plans. It never submits exchange orders. */
class IctEntryPlanner
{
    public function plan(float $referencePrice, ?array $zone, bool $triggerConfirmed, ?float $invalidationPrice = null): array
    {
        if (! is_finite($referencePrice) || $referencePrice <= 0) {
            return ['market' => ['status' => 'REJECTED', 'reason' => 'INVALID_REFERENCE_PRICE'], 'limit' => ['status' => 'REJECTED', 'reason' => 'INVALID_REFERENCE_PRICE']];
        }

        $maxDistanceBps = max(0.0, (float) config('ict.max_market_distance_bps', 35));
        $expiryMinutes = max(1, (int) config('ict.entry_expiry_minutes', 30));
        $validUntil = CarbonImmutable::now()->addMinutes($expiryMinutes)->toIso8601String();
        $zoneValid = $zone !== null
            && isset($zone['low'], $zone['high'])
            && is_numeric($zone['low'])
            && is_numeric($zone['high'])
            && is_finite((float) $zone['low'])
            && is_finite((float) $zone['high'])
            && (float) $zone['low'] > 0
            && (float) $zone['high'] > (float) $zone['low'];
        $zoneMid = $zoneValid ? (((float) $zone['low'] + (float) $zone['high']) / 2) : null;
        $marketDistance = $zoneMid === null ? null : abs($referencePrice - $zoneMid) / $referencePrice * 10000;
        $marketAllowed = $triggerConfirmed && $zoneValid && $marketDistance !== null && $marketDistance <= $maxDistanceBps;

        $market = [
            'entry_type' => 'MARKET',
            'status' => $marketAllowed ? 'PLANNED' : 'WAIT',
            'reason' => ! $zoneValid ? 'VALID_ENTRY_ZONE_REQUIRED' : (! $triggerConfirmed ? 'ENTRY_TRIGGER_NOT_CONFIRMED' : (($marketDistance ?? PHP_FLOAT_MAX) > $maxDistanceBps ? 'MARKET_ENTRY_TOO_FAR_FROM_ZONE' : null)),
            'reference_price' => $referencePrice,
            'max_distance_bps' => $maxDistanceBps,
            'distance_from_zone_bps' => $marketDistance,
            'valid_until' => $validUntil,
            'trigger_confirmed' => $triggerConfirmed,
            'cancel_condition' => 'MODEL_INVALIDATED_OR_EXPIRY',
            'execution_price_source' => 'LIVE_ASK_REQUIRED',
        ];

        $limitPrice = $zoneValid ? (float) ($zone['ce'] ?? $zoneMid) : null;
        if ($limitPrice !== null && (! is_finite($limitPrice) || $limitPrice < (float) $zone['low'] || $limitPrice > (float) $zone['high'])) {
            $limitPrice = null;
        }
        $limit = [
            'entry_type' => 'LIMIT',
            'status' => $limitPrice !== null && $limitPrice > 0 ? 'PLANNED' : 'UNAVAILABLE',
            'reason' => $limitPrice !== null && $limitPrice > 0 ? null : 'VALID_LIMIT_ZONE_REQUIRED',
            'limit_price' => $limitPrice,
            'zone_low' => $zoneValid ? (float) $zone['low'] : null,
            'zone_high' => $zoneValid ? (float) $zone['high'] : null,
            'valid_until' => $validUntil,
            'cancel_condition' => 'MODEL_INVALIDATED_OR_EXPIRY',
            'fill_policy' => 'TOUCH_IS_NOT_A_FILL',
            'invalidation_price' => $invalidationPrice,
        ];

        return ['market' => $market, 'limit' => $limit, 'order_submission_enabled' => false];
    }
}
