<?php

namespace App\\Services\\Analysis;

/** Builds a long-only structural plan. No plan is produced without a confirmed trigger. */
class IctStructuralTradePlanner
{
    public function plan(array $candles, array $structure, array $liquidity, array $displacements, array $zones, array $range): array
    {
        $last = $candles[array_key_last($candles)] ?? null;
        $zone = $zones['best_bullish_zone'] ?? null;
        if ($last === null || $zone === null) return $this->wait('NO_QUALIFIED_BULLISH_ZONE');
        $price = (float) $last['close'];
        $touch = (float) $last['low'] <= (float) $zone['high'] && (float) $last['high'] >= (float) $zone['low'];
        $trigger = (float) $last['close'] > (float) $last['open'];
        if (! $touch || ! $trigger) return $this->wait(! $touch ? 'WAITING_FOR_ZONE_RETEST' : 'WAITING_FOR_BULLISH_REACTION_CLOSE', $zone, $price);

        $swingLows = $structure['swings']['lows'] ?? [];
        $priorLows = array_values(array_filter($swingLows, fn (array $p): bool => $p['confirmation_index'] < count($candles)));
        $structuralLow = $priorLows === [] ? (float) $zone['low'] : (float) $priorLows[array_key_last($priorLows)]['price'];
        $invalidation = min((float) $zone['low'], $structuralLow);
        $atr = (float) (($displacements['latest_bullish']['atr'] ?? 0));
        $buffer = $atr > 0 ? $atr * (float) config('ict.stop_atr_buffer', 0.15) : 0.0;
        $stop = $invalidation - $buffer;
        $risk = $price - $stop;
        if ($risk <= 0) return $this->wait('INVALID_STRUCTURAL_STOP_GEOMETRY', $zone, $price);

        $targets = [];
        foreach (($liquidity['pools'] ?? []) as $pool) {
            if ($pool['side'] === 'BSL' && (float) $pool['level'] > $price) $targets[] = (float) $pool['level'];
        }
        foreach (($structure['swings']['highs'] ?? []) as $swing) {
            if ((float) $swing['price'] > $price) $targets[] = (float) $swing['price'];
        }
        $targets = array_values(array_unique($targets));
        sort($targets);
        $target1 = $targets[0] ?? ($price + $risk * (float) config('ict.target_1_r', 2.0));
        $target2 = $targets[1] ?? ($price + $risk * (float) config('ict.target_2_r', 3.0));
        if ($target2 <= $target1) $target2 = $price + $risk * (float) config('ict.target_2_r', 3.0);
        $rr = ($target1 - $price) / $risk;
        if ($rr < (float) config('ict.minimum_structural_rr', 1.5)) return $this->wait('STRUCTURAL_TARGET_REWARD_RISK_TOO_LOW', $zone, $price);

        return [
            'status' => 'ENTERABLE', 'direction' => 'LONG', 'entry' => $price,
            'stop_loss' => $stop, 'target1' => $target1, 'target2' => $target2,
            'risk_per_unit' => $risk, 'target1_r' => round($rr, 3),
            'entry_zone' => $zone, 'target_source' => $targets === [] ? 'R_MULTIPLE_FALLBACK' : 'NEAREST_CONFIRMED_OPPOSING_LIQUIDITY',
            'invalidation' => ['close_below' => $invalidation, 'buffer' => $buffer],
            'confirmation' => ['completed_bullish_reaction_candle' => true, 'zone_retest' => true],
            'entry_plans' => (new IctEntryPlanner)->plan($price, $zone, true, $stop),
            'order_submission_enabled' => false,
        ];
    }

    private function wait(string $reason, ?array $zone = null, ?float $price = null): array
    {
        return [
            'status' => 'WAIT', 'direction' => 'LONG', 'entry' => null, 'stop_loss' => null,
            'target1' => null, 'target2' => null, 'reason' => $reason,
            'watchlist_zone' => $zone, 'reference_price' => $price, 'order_submission_enabled' => false,
        ];
    }
}
