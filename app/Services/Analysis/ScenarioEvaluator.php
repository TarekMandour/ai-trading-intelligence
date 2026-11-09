<?php

namespace App\\Services\\Analysis;

/**
 * Deterministic long-only setup confirmation. This component creates candidate
 * levels only from completed entry-timeframe candles; the AI layer never sets levels.
 */
class ScenarioEvaluator
{
    /**
     * @param array<string,mixed> $regime
     * @param array<int,array{open:float,high:float,low:float,close:float,volume:float}> $candles
     * @return array<int,array{scenario:string,status:string,reasons:array<int,string>,candidate:?array<string,float>}>
     */
    public function evaluate(array $regime, array $candles = []): array
    {
        $trend = $this->trendPullback($regime, $candles);
        $breakout = $this->breakoutRetest($regime, $candles);
        $range = $this->rangeMeanReversion($regime, $candles);
        $momentum = $this->momentumExpansion($regime, $candles);

        return [$trend, $breakout, $range, $momentum];
    }

    private function trendPullback(array $regime, array $candles): array
    {
        if (($regime['direction'] ?? null) !== 'BULLISH' || ($regime['behavior'] ?? null) !== 'TRENDING') {
            return $this->result('TREND_PULLBACK_CONTINUATION', 'REJECTED', 'BULLISH_TREND_CONTEXT_MISSING');
        }
        if (count($candles) < 50) {
            return $this->result('TREND_PULLBACK_CONTINUATION', 'WAIT', 'INSUFFICIENT_SETUP_CANDLES');
        }

        $closes = array_column($candles, 'close');
        $ema20 = $this->ema($closes, 20);
        $ema50 = $this->ema($closes, 50);
        $last = $candles[array_key_last($candles)];
        $previous = $candles[count($candles) - 2];
        $pullback = array_slice($candles, -7, 5);
        $touchedEma = min(array_column($pullback, 'low')) <= $ema20 * 1.005;
        $volumeConfirmed = $last['volume'] >= $this->average(array_column(array_slice($candles, -21, 20), 'volume')) * 1.2;

        if ($ema20 <= $ema50 || $last['close'] <= $ema20 || ! $touchedEma || $last['close'] <= $previous['high'] || ! $volumeConfirmed) {
            return $this->result('TREND_PULLBACK_CONTINUATION', 'WAIT', 'PULLBACK_OR_BULLISH_RECLAIM_NOT_CONFIRMED');
        }

        $stop = min(array_column(array_slice($candles, -6, 5), 'low'));
        return $this->candidateResult('TREND_PULLBACK_CONTINUATION', (float) $last['close'], (float) $stop, 1.5, 2.5);
    }

    private function breakoutRetest(array $regime, array $candles): array
    {
        if (($regime['direction'] ?? null) !== 'BULLISH') {
            return $this->result('BREAKOUT_RETEST', 'REJECTED', 'BULLISH_CONTEXT_MISSING');
        }
        if (count($candles) < 30) {
            return $this->result('BREAKOUT_RETEST', 'WAIT', 'INSUFFICIENT_SETUP_CANDLES');
        }

        $reference = array_slice($candles, -21, 20);
        $level = max(array_column($reference, 'high'));
        $recent = array_slice($candles, -3, 2);
        $last = $candles[array_key_last($candles)];
        $avgVolume = $this->average(array_column(array_slice($candles, -21, 20), 'volume'));
        $retested = min(array_column($recent, 'low')) <= $level * 1.005;
        if (! $retested || $last['close'] <= $level || $last['close'] <= $last['open'] || $last['volume'] < $avgVolume * 1.2) {
            return $this->result('BREAKOUT_RETEST', 'WAIT', 'BREAKOUT_RETEST_NOT_CONFIRMED');
        }

        $stop = min(array_column(array_slice($candles, -5, 4), 'low'));
        return $this->candidateResult('BREAKOUT_RETEST', (float) $last['close'], (float) $stop, 1.5, 2.5);
    }

    private function rangeMeanReversion(array $regime, array $candles): array
    {
        if (($regime['direction'] ?? null) !== 'RANGING') {
            return $this->result('RANGE_MEAN_REVERSION', 'REJECTED', 'RANGE_CONTEXT_MISSING');
        }
        if (count($candles) < 25) {
            return $this->result('RANGE_MEAN_REVERSION', 'WAIT', 'INSUFFICIENT_SETUP_CANDLES');
        }

        $window = array_slice($candles, -21, 20);
        $floor = min(array_column($window, 'low'));
        $ceiling = max(array_column($window, 'high'));
        $width = $ceiling - $floor;
        $last = $candles[array_key_last($candles)];
        if ($width <= 0 || $last['close'] > $floor + ($width * .2) || $last['close'] <= $last['open']) {
            return $this->result('RANGE_MEAN_REVERSION', 'WAIT', 'LOWER_RANGE_REVERSAL_NOT_CONFIRMED');
        }

        $stop = min(array_column(array_slice($candles, -5, 4), 'low'));
        $risk = $last['close'] - $stop;
        if ($risk <= 0 || ($floor + $width * .5) - $last['close'] < $risk * 1.5) {
            return $this->result('RANGE_MEAN_REVERSION', 'WAIT', 'RANGE_REWARD_TO_RISK_INSUFFICIENT');
        }

        return [
            'scenario' => 'RANGE_MEAN_REVERSION',
            'status' => 'ENTERABLE',
            'reasons' => ['LOWER_RANGE_BULLISH_REVERSAL'],
            'candidate' => ['entry' => (float) $last['close'], 'stop' => (float) $stop, 'target1' => (float) ($floor + $width * .5), 'target2' => (float) $ceiling, 'risk_reward' => (float) (($floor + $width * .5 - $last['close']) / $risk)],
        ];
    }

    private function momentumExpansion(array $regime, array $candles): array
    {
        if (($regime['volatility'] ?? null) !== 'HIGH' || ($regime['direction'] ?? null) !== 'BULLISH') {
            return $this->result('MOMENTUM_VOLATILITY_EXPANSION', 'REJECTED', 'BULLISH_HIGH_VOLATILITY_CONTEXT_MISSING');
        }
        if (count($candles) < 25) {
            return $this->result('MOMENTUM_VOLATILITY_EXPANSION', 'WAIT', 'INSUFFICIENT_SETUP_CANDLES');
        }

        $window = array_slice($candles, -21, 20);
        $last = $candles[array_key_last($candles)];
        $breakout = $last['close'] > max(array_column($window, 'high'));
        $volumeConfirmed = $last['volume'] >= $this->average(array_column($window, 'volume')) * 1.5;
        if (! $breakout || ! $volumeConfirmed || $last['close'] <= $last['open']) {
            return $this->result('MOMENTUM_VOLATILITY_EXPANSION', 'WAIT', 'MOMENTUM_BREAKOUT_NOT_CONFIRMED');
        }

        $stop = min(array_column(array_slice($candles, -5, 4), 'low'));
        return $this->candidateResult('MOMENTUM_VOLATILITY_EXPANSION', (float) $last['close'], (float) $stop, 1.5, 2.5);
    }

    private function candidateResult(string $scenario, float $entry, float $stop, float $target1R, float $target2R): array
    {
        $risk = $entry - $stop;
        if ($risk <= 0) {
            return $this->result($scenario, 'REJECTED', 'INVALID_LONG_STOP_GEOMETRY');
        }

        return [
            'scenario' => $scenario,
            'status' => 'ENTERABLE',
            'reasons' => ['DETERMINISTIC_SETUP_CONFIRMED'],
            'candidate' => [
                'entry' => $entry,
                'stop' => $stop,
                'target1' => $entry + ($risk * $target1R),
                'target2' => $entry + ($risk * $target2R),
                'risk_reward' => $target1R,
            ],
        ];
    }

    private function result(string $scenario, string $status, string $reason): array
    {
        return ['scenario' => $scenario, 'status' => $status, 'reasons' => [$reason], 'candidate' => null];
    }

    private function ema(array $values, int $period): float
    {
        $slice = array_slice($values, -($period * 3));
        $ema = array_sum(array_slice($slice, 0, $period)) / $period;
        $multiplier = 2 / ($period + 1);
        foreach (array_slice($slice, $period) as $value) {
            $ema = (($value - $ema) * $multiplier) + $ema;
        }

        return $ema;
    }

    private function average(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }
}
