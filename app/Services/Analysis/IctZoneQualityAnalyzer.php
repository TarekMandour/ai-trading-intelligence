<?php

namespace App\Services\Analysis;

/** Scores linked FVG/OB zones using impulse, volume, structure and liquidity evidence. */
class IctZoneQualityAnalyzer
{
    public function analyze(array $candles, array $displacements, array $structure, array $liquidity): array
    {
        $zones = [];
        foreach (($displacements['events'] ?? []) as $impulse) {
            if ($impulse['direction'] !== 'BULLISH') continue;
            $i = (int) $impulse['index'];
            $prior = $candles[$i - 1] ?? null;
            $third = $candles[$i + 1] ?? null;
            if ($prior !== null && $third !== null) {
                $low = (float) $prior['high'];
                $high = (float) $third['low'];
                if ($high > $low) {
                    $zones[] = $this->zone('BULLISH_FVG', $low, $high, $i, $impulse, $structure, $liquidity, (int) ($third['close_time'] ?? 0));
                }
            }

            for ($j = $i - 1; $j >= max(0, $i - 5); $j--) {
                if ((float) $candles[$j]['close'] >= (float) $candles[$j]['open']) continue;
                $low = (float) $candles[$j]['low'];
                $high = (float) $candles[$j]['open'];
                if ($high > $low) $zones[] = $this->zone('BULLISH_ORDER_BLOCK', $low, $high, $i, $impulse, $structure, $liquidity, (int) ($candles[$i]['close_time'] ?? 0), $j);
                break;
            }
        }

        $lifecycle = new IctZoneLifecycleAnalyzer;
        foreach ($zones as &$zone) {
            $afterCreation = array_values(array_filter(
                $candles,
                fn (array $c): bool => (int) ($c['close_time'] ?? 0) > (int) $zone['created_at'],
            ));
            $zone['lifecycle_evidence'] = $zone['type'] === 'BULLISH_FVG'
                ? $lifecycle->bullishFvg($zone, $afterCreation)
                : $lifecycle->bullishOrderBlock($zone, $afterCreation);
            $zone['lifecycle'] = $zone['lifecycle_evidence']['status'];
            $zone['touch_count'] = $zone['lifecycle_evidence']['touch_count'];
        }
        unset($zone);
        $zones = array_values(array_filter($zones, fn (array $z): bool =>
            $z['quality_score'] >= (int) config('ict.minimum_zone_quality', 45)
            && ! in_array($z['lifecycle'], ['INVALIDATED', 'FULLY_FILLED'], true)
        ));
        usort($zones, fn (array $a, array $b): int => $b['quality_score'] <=> $a['quality_score']);
        return ['zones' => $zones, 'best_bullish_zone' => $zones[0] ?? null];
    }

    private function zone(string $type, float $low, float $high, int $impulseIndex, array $impulse, array $structure, array $liquidity, int $createdAt, ?int $originIndex = null): array
    {
        $hasBreak = collect($structure['events'] ?? [])->contains(fn (array $e): bool => $e['direction'] === 'BULLISH' && $e['break_index'] === $impulseIndex);
        $linkedSweep = collect($liquidity['sweeps'] ?? [])->contains(fn (array $sweep): bool =>
            $sweep['side'] === 'SSL'
            && $sweep['index'] < $impulseIndex
            && $impulseIndex - $sweep['index'] <= max(1, (int) config('ict.sequence_max_bars_after_sweep', 5))
        );
        $score = (int) round(
            min(2.5, (float) $impulse['range_atr']) / 2.5 * 30
            + min(1, (float) $impulse['body_ratio']) * 25
            + min(2, (float) $impulse['volume_ratio']) / 2 * 20
            + ($hasBreak ? 15 : 0)
            + ($linkedSweep ? 10 : 0)
        );
        return [
            'type' => $type, 'direction' => 'BULLISH', 'low' => $low, 'high' => $high,
            'ce' => ($low + $high) / 2, 'created_at' => $createdAt,
            'origin_index' => $originIndex, 'impulse_index' => $impulseIndex,
            'impulse' => $impulse, 'linked_structure_break' => $hasBreak,
            'linked_liquidity_sweep' => $linkedSweep, 'quality_score' => $score,
            'quality_grade' => $score >= 80 ? 'A' : ($score >= 65 ? 'B' : ($score >= 50 ? 'C' : 'D')),
            'lifecycle' => 'ACTIVE',
        ];
    }
}
