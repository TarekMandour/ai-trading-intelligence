<?php

namespace App\Services\Analysis;

/**
 * Finds early, research-only opportunities before the strict entry trigger.
 * It never promotes a watchlist setup to an executable trade.
 */
class IctOpportunityWatchlist
{
    public function evaluate(array $context, array $setup, array $advanced, array $entry): ?array
    {
        $price = (float) ($entry['last_close'] ?? 0);
        if ($price <= 0) return null;

        $structureBias = $context['structure_bias'] ?? 'UNCERTAIN';
        $contextBias = $context['bias'] ?? 'UNCERTAIN';
        $mss = (bool) ($setup['mss']['confirmed'] ?? false);
        $sequence = ($setup['sequence']['status'] ?? null) === 'CONFIRMED';
        $breaker = is_array($advanced['breaker_block'] ?? null) && ($advanced['breaker_block']['direction'] ?? null) === 'BULLISH';

        // Require observable bullish structure evidence; a zone by itself is not a setup.
        if (! in_array($structureBias, ['BULLISH', 'RANGING'], true)
            || ! ($mss || $sequence || $breaker)
            || $contextBias === 'BEARISH') {
            return null;
        }

        $zones = [];
        $fvg = $setup['fvg'] ?? null;
        $fvgState = $setup['fvg_lifecycle']['status'] ?? 'ACTIVE';
        if (is_array($fvg) && ! in_array($fvgState, ['INVALIDATED', 'FULLY_FILLED'], true)) {
            $zones[] = ['type' => 'BULLISH_FVG', 'zone' => $fvg, 'lifecycle' => $setup['fvg_lifecycle'] ?? null];
        }
        $ob = $setup['ob'] ?? null;
        $obState = $setup['ob_lifecycle']['status'] ?? 'ACTIVE';
        if (is_array($ob) && ! in_array($obState, ['INVALIDATED'], true)) {
            $zones[] = ['type' => 'BULLISH_ORDER_BLOCK', 'zone' => $ob, 'lifecycle' => $setup['ob_lifecycle'] ?? null];
        }
        foreach (['breaker_block' => 'BULLISH_BREAKER', 'ote' => 'BULLISH_OTE'] as $key => $type) {
            $zone = $advanced[$key] ?? null;
            if (is_array($zone) && isset($zone['low'], $zone['high']) && in_array(($zone['direction'] ?? 'LONG'), ['BULLISH', 'LONG'], true)) {
                $zones[] = ['type' => $type, 'zone' => $zone, 'lifecycle' => null];
            }
        }
        if ($zones === []) return null;

        $maxDistancePct = max(0.1, (float) config('ict.watchlist_max_zone_distance_pct', 3.0));
        $ranked = [];
        foreach ($zones as $item) {
            $low = (float) ($item['zone']['low'] ?? 0);
            $high = (float) ($item['zone']['high'] ?? 0);
            if ($low <= 0 || $high <= $low) continue;
            $distance = $price < $low ? (($low - $price) / $price) * 100
                : ($price > $high ? (($price - $high) / $price) * 100 : 0.0);
            if ($distance <= $maxDistancePct) {
                $ranked[] = $item + ['distance_pct' => $distance];
            }
        }
        if ($ranked === []) return null;

        usort($ranked, fn (array $a, array $b): int => $a['distance_pct'] <=> $b['distance_pct']);
        $selected = $ranked[0];
        $zone = $selected['zone'];
        $reasons = [];
        if ($mss) $reasons[] = 'BULLISH_MSS_OBSERVED';
        if ($sequence) $reasons[] = 'ORDERED_BULLISH_SEQUENCE_CONFIRMED';
        if ($breaker) $reasons[] = 'BULLISH_BREAKER_RETEST_CANDIDATE';
        $reasons[] = $selected['distance_pct'] == 0.0 ? 'PRICE_INSIDE_ACTIVE_ZONE' : 'PRICE_NEAR_ACTIVE_ZONE';
        if (! ($entry['bullish_trigger'] ?? false)) $reasons[] = 'WAITING_FOR_BULLISH_ENTRY_TRIGGER';

        return [
            'status' => 'WATCHLIST',
            'direction' => 'LONG',
            'reference_price' => $price,
            'zone_type' => $selected['type'],
            'zone' => ['low' => (float) $zone['low'], 'high' => (float) $zone['high'], 'ce' => isset($zone['ce']) ? (float) $zone['ce'] : (((float) $zone['low'] + (float) $zone['high']) / 2)],
            'distance_to_zone_pct' => round($selected['distance_pct'], 3),
            'structure' => ['context_bias' => $contextBias, 'structure_bias' => $structureBias, 'mss_confirmed' => $mss, 'sequence_confirmed' => $sequence],
            'lifecycle' => $selected['lifecycle'],
            'reasons' => $reasons,
            'required_confirmation' => ['completed_bullish_entry_candle', 'zone_holds_without_invalidation'],
            'invalidation' => 'A completed close below the selected bullish zone low invalidates this watchlist setup.',
            'entry_authorized' => false,
        ];
    }
}
