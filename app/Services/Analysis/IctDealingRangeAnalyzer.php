<?php

namespace App\Services\Analysis;

/** Derives premium/discount from the latest confirmed directional swing leg. */
class IctDealingRangeAnalyzer
{
    public function __construct(private readonly IctSwingPointDetector $pivots = new IctSwingPointDetector) {}

    public function analyze(array $candles, array $structure): array
    {
        $swings = $structure['swings'] ?? $this->pivots->detect($candles);
        $bias = $structure['bias'] ?? 'UNCERTAIN';
        $low = $high = null;
        $lowPoint = $highPoint = null;
        if ($bias === 'BULLISH') {
            foreach (array_reverse($swings['highs']) as $candidateHigh) {
                foreach (array_reverse($swings['lows']) as $candidateLow) {
                    if ($candidateLow['index'] < $candidateHigh['index']) { $lowPoint = $candidateLow; $highPoint = $candidateHigh; break 2; }
                }
            }
        } elseif ($bias === 'BEARISH') {
            foreach (array_reverse($swings['lows']) as $candidateLow) {
                foreach (array_reverse($swings['highs']) as $candidateHigh) {
                    if ($candidateHigh['index'] < $candidateLow['index']) { $highPoint = $candidateHigh; $lowPoint = $candidateLow; break 2; }
                }
            }
        } else {
            $all = array_merge($swings['highs'], $swings['lows']);
            usort($all, fn (array $a, array $b): int => $a['index'] <=> $b['index']);
            if (count($all) >= 2) {
                $a = $all[count($all) - 2]; $b = $all[count($all) - 1];
                if ($a['type'] === 'LOW' && $b['type'] === 'HIGH') { $lowPoint = $a; $highPoint = $b; }
                if ($a['type'] === 'HIGH' && $b['type'] === 'LOW') { $highPoint = $a; $lowPoint = $b; }
            }
        }
        if ($lowPoint === null || $highPoint === null || $highPoint['price'] <= $lowPoint['price']) {
            return ['status' => 'UNAVAILABLE', 'location' => 'UNCERTAIN', 'reason' => 'NO_VALID_CONFIRMED_DIRECTIONAL_SWING_LEG'];
        }
        $low = (float) $lowPoint['price']; $high = (float) $highPoint['price'];
        $eq = ($low + $high) / 2;
        $price = (float) $candles[array_key_last($candles)]['close'];
        $location = $price < $eq ? 'DISCOUNT' : ($price > $eq ? 'PREMIUM' : 'EQUILIBRIUM');
        return [
            'status' => 'AVAILABLE', 'low' => $low, 'high' => $high, 'equilibrium' => $eq,
            'location' => $location, 'direction' => $bias,
            'range_indices' => ['low' => $lowPoint['index'], 'high' => $highPoint['index']],
            'source' => 'LATEST_CONFIRMED_DIRECTIONAL_SWING_LEG',
        ];
    }
}
