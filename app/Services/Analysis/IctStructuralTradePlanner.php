<?php

namespace App\Services\Analysis;

/** Builds a long-only structural plan. No plan is produced without a confirmed trigger. */
class IctStructuralTradePlanner
{
    public function plan(array $candles, array $structure, array $liquidity, array $displacements, array $zones, array $range, array $entryCandles = []): array
    {
        $entryCandles = $entryCandles !== [] ? $entryCandles : $candles;
        $last = $entryCandles[array_key_last($entryCandles)] ?? null;
        $zone = $zones['best_bullish_zone'] ?? null;
        if ($last === null || $zone === null) return $this->wait('NO_QUALIFIED_BULLISH_ZONE');
        $price = (float) $last['close'];
        if ((int) ($last['close_time'] ?? 0) <= (int) ($zone['created_at'] ?? 0)) return $this->wait('ENTRY_CANDLE_PRECEDES_ZONE_CREATION', $zone, $price);
        $touch = (float) $last['low'] <= (float) $zone['high'] && (float) $last['high'] >= (float) $zone['low'];
        $previousEntry = $entryCandles[count($entryCandles) - 2] ?? null;
        $trigger = $previousEntry !== null
            && (float) $last['close'] > (float) $last['open']
            && (float) $last['close'] > (float) $previousEntry['high'];
        if (! $touch || ! $trigger) return $this->wait(! $touch ? 'WAITING_FOR_ZONE_RETEST' : 'WAITING_FOR_BULLISH_REACTION_CLOSE', $zone, $price);

        $swingLows = $structure['swings']['lows'] ?? [];
        $zoneOriginIndex = (int) ($zone['origin_index'] ?? $zone['impulse_index'] ?? -1);
        $priorLows = array_values(array_filter($swingLows, fn (array $p): bool =>
            $p['confirmation_index'] < count($candles) && $p['index'] >= $zoneOriginIndex
        ));
        $structuralLow = $priorLows === [] ? (float) $zone['low'] : (float) $priorLows[array_key_last($priorLows)]['price'];
        $invalidation = min((float) $zone['low'], $structuralLow);
        $atr = (float) (($displacements['latest_bullish']['atr'] ?? 0));
        $buffer = $atr > 0 ? $atr * (float) config('ict.stop_atr_buffer', 0.15) : 0.0;
        $stop = $invalidation - $buffer;
        $risk = $price - $stop;
        if ($risk <= 0) return $this->wait('INVALID_STRUCTURAL_STOP_GEOMETRY', $zone, $price);

        $targets = [];
        foreach (($liquidity['pools'] ?? []) as $pool) {
            if ($pool['side'] !== 'BSL' || (float) $pool['level'] <= $price) continue;
            $alreadySwept = collect($liquidity['sweeps'] ?? [])->contains(fn (array $sweep): bool =>
                $sweep['side'] === 'BSL'
                && abs((float) $sweep['level'] - (float) $pool['level']) / max(0.00000001, (float) $pool['level']) * 10000 <= (float) config('ict.equal_level_tolerance_bps', 8)
                && $sweep['index'] > max($pool['pivot_indices'])
            );
            if (! $alreadySwept) $targets[] = (float) $pool['level'];
        }
        $alreadyBroken = array_map('intval', $structure['broken_levels']['highs'] ?? []);
        foreach (($structure['swings']['highs'] ?? []) as $swing) {
            if ((float) $swing['price'] > $price && ! in_array((int) $swing['index'], $alreadyBroken, true)) $targets[] = (float) $swing['price'];
        }
        $targets = array_values(array_unique($targets));
        sort($targets);
        $target1 = $targets[0] ?? ($price + $risk * (float) config('ict.target_1_r', 2.0));
        $target2 = $targets[1] ?? ($price + $risk * (float) config('ict.target_2_r', 3.0));
        if ($target2 <= $target1) {
            $target2 = $target1 + ($risk * max(0.5, (float) config('ict.target_2_r', 3.0) - (float) config('ict.target_1_r', 2.0)));
        }
        $rr = ($target1 - $price) / $risk;
        if ($rr < (float) config('ict.minimum_structural_rr', 1.5)) return $this->wait('STRUCTURAL_TARGET_REWARD_RISK_TOO_LOW', $zone, $price);

        return [
            'status' => 'ENTERABLE', 'direction' => 'LONG', 'entry' => $price,
            'stop_loss' => $stop, 'target1' => $target1, 'target2' => $target2,
            'risk_per_unit' => $risk, 'target1_r' => round($rr, 3),
            'entry_zone' => $zone, 'dealing_range' => $range,
            'dealing_range_location' => $range['location'] ?? 'UNCERTAIN',
            'dealing_range_confluence' => ($range['location'] ?? null) === 'DISCOUNT' ? 'SUPPORTIVE_FOR_LONG' : (($range['location'] ?? null) === 'PREMIUM' ? 'CAUTION_LONG_ENTRY_IN_PREMIUM' : 'RANGE_UNAVAILABLE'),
            'target_source' => $targets === [] ? 'R_MULTIPLE_FALLBACK' : 'NEAREST_CONFIRMED_OPPOSING_LIQUIDITY',
            'invalidation' => ['close_below' => $invalidation, 'buffer' => $buffer],
            'confirmation' => ['completed_bullish_reaction_candle' => true, 'close_above_previous_entry_high' => true, 'zone_retest' => true],
            'entry_plans' => (new IctEntryPlanner)->plan($price, $zone, true, $stop),
            'order_submission_enabled' => false,
        ];
    }

    private function wait(string $reason, ?array $zone = null, ?float $price = null): array
    {
        return [
            'status' => 'WAIT', 'direction' => 'LONG', 'entry' => null, 'stop_loss' => null,
            'target1' => null, 'target2' => null, 'reason' => $reason,
            'watchlist_zone' => $zone, 'reference_price' => $price, 'dealing_range' => null, 'order_submission_enabled' => false,
        ];
    }
}
