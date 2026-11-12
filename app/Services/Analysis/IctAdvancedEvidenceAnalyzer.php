<?php

namespace App\Services\Analysis;

use Carbon\CarbonImmutable;

/**
 * Optional ICT confluence evidence. These observations do not independently
 * authorize a trade and remain disabled as hard gates unless explicitly configured.
 */
class IctAdvancedEvidenceAnalyzer
{
    public function analyze(array $candles, array $referenceCandles = []): array
    {
        $swings = $this->swings($candles);

        return [
            'equal_levels' => $this->equalLevels($swings),
            'ote' => $this->ote($swings),
            'rejection_block' => $this->rejectionBlock($candles),
            'breaker_block' => $this->breakerBlock($candles),
            'session_models' => $this->sessionModels($candles),
            'smt' => $this->smt($candles, $referenceCandles),
        ];
    }

    private function swings(array $candles): array
    {
        $left = max(1, (int) config('ict.swing_left', 2));
        $right = max(1, (int) config('ict.swing_right', 2));
        $highs = $lows = [];
        for ($i = $left; $i < count($candles) - $right; $i++) {
            $window = array_slice($candles, $i - $left, $left + $right + 1);
            $others = array_merge(array_slice($window, 0, $left), array_slice($window, $left + 1));
            if ((float) $candles[$i]['high'] > max(array_column($others, 'high'))) $highs[] = ['index' => $i, 'price' => (float) $candles[$i]['high']];
            if ((float) $candles[$i]['low'] < min(array_column($others, 'low'))) $lows[] = ['index' => $i, 'price' => (float) $candles[$i]['low']];
        }
        return compact('highs', 'lows');
    }

    private function equalLevels(array $swings): array
    {
        $toleranceBps = max(0.0, (float) config('ict.equal_level_tolerance_bps', 8));
        $groups = [];
        foreach (['highs' => 'BSL', 'lows' => 'SSL'] as $key => $type) {
            $items = $swings[$key];
            for ($i = 0; $i < count($items); $i++) {
                for ($j = $i + 1; $j < count($items); $j++) {
                    $a = $items[$i]['price'];
                    $b = $items[$j]['price'];
                    $mid = ($a + $b) / 2;
                    if ($mid > 0 && abs($a - $b) / $mid * 10000 <= $toleranceBps) {
                        $groups[] = ['type' => $type, 'level' => $mid, 'touches' => 2, 'swing_indices' => [$items[$i]['index'], $items[$j]['index']], 'status' => 'POTENTIAL_LIQUIDITY'];
                    }
                }
            }
        }
        return $groups;
    }

    private function ote(array $swings): ?array
    {
        $low = null;
        $high = null;
        foreach ($swings['lows'] as $candidateLow) {
            foreach ($swings['highs'] as $candidateHigh) {
                if ($candidateLow['index'] < $candidateHigh['index'] && ($high === null || $candidateHigh['index'] > $high['index'])) {
                    $low = $candidateLow;
                    $high = $candidateHigh;
                }
            }
        }
        if ($low === null || $high === null || $high['price'] <= $low['price']) return null;
        $range = $high['price'] - $low['price'];
        $shallow = (float) config('ict.ote_shallow', .62);
        $deep = (float) config('ict.ote_deep', .79);
        return [
            'direction' => 'LONG',
            'swing_low' => $low['price'],
            'swing_high' => $high['price'],
            'low' => $high['price'] - ($range * $deep),
            'high' => $high['price'] - ($range * $shallow),
            'status' => 'CONFLUENCE_ONLY',
        ];
    }

    private function rejectionBlock(array $candles): ?array
    {
        $last = $candles[array_key_last($candles)] ?? null;
        if ($last === null) return null;
        $range = (float) $last['high'] - (float) $last['low'];
        if ($range <= 0) return null;
        $lowerWick = min((float) $last['open'], (float) $last['close']) - (float) $last['low'];
        $closeLocation = ((float) $last['close'] - (float) $last['low']) / $range;
        $wickRatio = $lowerWick / $range;
        $valid = $wickRatio >= (float) config('ict.rejection_wick_ratio', .55)
            && $closeLocation >= (float) config('ict.rejection_close_location', .7);
        return $valid ? ['direction' => 'BULLISH', 'low' => (float) $last['low'], 'high' => min((float) $last['open'], (float) $last['close']), 'wick_ratio' => $wickRatio, 'close_location' => $closeLocation, 'status' => 'CANDIDATE'] : null;
    }

    private function breakerBlock(array $candles): ?array
    {
        $count = count($candles);
        if ($count < 5) return null;
        $last = $candles[$count - 1];
        for ($i = $count - 3; $i >= max(0, $count - 12); $i--) {
            $candidate = $candles[$i];
            if ((float) $candidate['close'] >= (float) $candidate['open']) continue;
            $broken = false;
            for ($j = $i + 1; $j < $count - 1; $j++) {
                if ((float) $candles[$j]['close'] > (float) $candidate['high']) {
                    $broken = true;
                    break;
                }
            }
            $zoneLow = (float) $candidate['open'];
            $zoneHigh = (float) $candidate['high'];
            $retest = (float) $last['low'] <= $zoneHigh && (float) $last['high'] >= $zoneLow;
            if ($broken && $retest) return ['direction' => 'BULLISH', 'low' => $zoneLow, 'high' => $zoneHigh, 'source_index' => $i, 'status' => 'RETEST_CANDIDATE'];
        }
        return null;
    }

    private function sessionModels(array $candles): array
    {
        $windows = config('ict.session_windows', []);
        if (! is_array($windows) || $windows === []) return ['status' => 'INACTIVE_NOT_CONFIGURED', 'models' => []];
        $last = $candles[array_key_last($candles)];
        $timezone = (string) config('ict.session_timezone', 'UTC');
        $time = CarbonImmutable::createFromTimestampMs((int) $last['close_time'])->setTimezone($timezone);
        $active = [];
        foreach ($windows as $model => $window) {
            if (! isset($window['start'], $window['end'])) continue;
            $clock = $time->format('H:i');
            if ($clock >= $window['start'] && $clock <= $window['end']) $active[] = (string) $model;
        }
        return ['status' => $active === [] ? 'OUTSIDE_CONFIGURED_WINDOWS' : 'ACTIVE', 'timezone' => $timezone, 'models' => $active, 'evaluated_at' => $time->toIso8601String()];
    }

    private function smt(array $candles, array $referenceCandles): array
    {
        if ($referenceCandles === []) return ['status' => 'NOT_CONFIGURED', 'reason' => 'SMT_REFERENCE_ASSET_REQUIRED'];
        if (count($referenceCandles) !== count($candles)) return ['status' => 'UNAVAILABLE', 'reason' => 'SMT_SERIES_NOT_SYNCHRONIZED'];

        foreach ($candles as $index => $candle) {
            if ((int) ($candle['close_time'] ?? -1) !== (int) ($referenceCandles[$index]['close_time'] ?? -2)) {
                return ['status' => 'UNAVAILABLE', 'reason' => 'SMT_CLOSE_TIMESTAMPS_NOT_ALIGNED'];
            }
        }

        $assetSwings = $this->swings($candles)['lows'];
        $referenceSwings = $this->swings($referenceCandles)['lows'];
        if (count($assetSwings) < 2 || count($referenceSwings) < 2) {
            return ['status' => 'INSUFFICIENT', 'reason' => 'SMT_REQUIRES_TWO_CONFIRMED_SWING_LOWS_PER_SERIES'];
        }

        $assetPair = array_slice($assetSwings, -2);
        $referencePair = array_slice($referenceSwings, -2);
        $indexTolerance = max(0, (int) config('ict.smt_index_tolerance', 3));
        foreach ([0, 1] as $position) {
            if (abs($assetPair[$position]['index'] - $referencePair[$position]['index']) > $indexTolerance) {
                return ['status' => 'UNAVAILABLE', 'reason' => 'SMT_SWING_POINTS_NOT_TIME_PAIRED'];
            }
        }

        $assetLowerLow = $assetPair[1]['price'] < $assetPair[0]['price'];
        $referenceLowerLow = $referencePair[1]['price'] < $referencePair[0]['price'];
        $bullishDivergence = $assetLowerLow && ! $referenceLowerLow;

        return [
            'status' => $bullishDivergence ? 'BULLISH_DIVERGENCE' : 'NO_BULLISH_DIVERGENCE',
            'direction' => $bullishDivergence ? 'BULLISH' : null,
            'asset_lower_low' => $assetLowerLow,
            'reference_lower_low' => $referenceLowerLow,
            'asset_swing_indices' => array_column($assetPair, 'index'),
            'reference_swing_indices' => array_column($referencePair, 'index'),
            'reference_comparison' => 'POSITIVE_CORRELATION_ASSUMED',
            'trade_gate' => false,
        ];
    }
}
