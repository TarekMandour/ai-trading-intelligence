<?php

namespace App\Services\Analysis;

/** Deterministic baseline. Inputs are completed candles only; no future candles are read. */
class DeterministicAnalysisEngine
{
    /** @param array<int, array{open_time:int,close_time:int,open:float,high:float,low:float,close:float,volume:float}> $candles
     * @return array{direction:string,volatility:string,behavior:string,reasons:array<int,string>,candidate:?array<string,mixed>} */
    public function evaluate(array $candles): array
    {
        if (count($candles) < 20 || collect($candles)->contains(fn (array $c): bool => $c['high'] < max($c['open'], $c['close']) || $c['low'] > min($c['open'], $c['close']) || $c['close_time'] > now()->getTimestampMs())) {
            return ['direction' => 'UNCERTAIN', 'volatility' => 'NORMAL', 'behavior' => 'UNCERTAIN', 'reasons' => ['INSUFFICIENT_OR_FORMING_CANDLES'], 'candidate' => null];
        }
        $closes = array_column($candles, 'close');
        $first = $closes[0];
        $last = end($closes);
        $ranges = array_map(fn (array $c): float => ($c['high'] - $c['low']) / max($c['close'], 0.00000001), $candles);
        $volatility = array_sum($ranges) / count($ranges) > 0.02 ? 'HIGH' : 'NORMAL';
        $direction = $last > $first * 1.01 ? 'BULLISH' : ($last < $first * .99 ? 'BEARISH' : 'RANGING');
        $behavior = abs($last - $first) / max(array_sum(array_map(fn ($a, $b) => abs($a - $b), array_slice($closes, 1), $closes)), 0.00000001) > .35 ? 'TRENDING' : 'CHOPPY';
        if ($direction !== 'BULLISH' || $behavior !== 'TRENDING') {
            return compact('direction', 'volatility', 'behavior') + ['reasons' => ['NO_LONG_SCENARIO_CONFIRMATION'], 'candidate' => null];
        }
        $entry = $last;
        $stop = min(array_slice(array_column($candles, 'low'), -10));
        $risk = $entry - $stop;
        if ($risk <= 0) {
            return compact('direction', 'volatility', 'behavior') + ['reasons' => ['INVALID_STOP_GEOMETRY'], 'candidate' => null];
        }
        $target1 = $entry + 2 * $risk;
        $target2 = $entry + 3 * $risk;
        $fee = $entry * .002;
        $rr = (($target1 - $entry) - $fee) / ($risk + $fee);
        if ($rr < 1.5) {
            return compact('direction', 'volatility', 'behavior') + ['reasons' => ['COST_ADJUSTED_RISK_REWARD_FAILED'], 'candidate' => null];
        }

        return compact('direction', 'volatility', 'behavior') + ['reasons' => ['TREND_CONTINUATION_BASELINE'], 'candidate' => compact('entry', 'stop', 'target1', 'target2', 'rr', 'fee')];
    }
}
