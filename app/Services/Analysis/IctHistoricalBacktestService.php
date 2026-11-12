<?php

namespace App\Services\Analysis;

/**
 * OHLCV-only historical replay. Limit touches are modeled assumptions, never
 * exchange-confirmed fills. Same-bar stop/target ambiguity is resolved stop-first.
 */
class IctHistoricalBacktestService
{
    public function __construct(private readonly IctComprehensiveEngine $engine) {}

    public function run(array $candlesByRole, string $profile, array $assumptions = []): array
    {
        foreach (['context', 'structure', 'setup', 'entry'] as $role) {
            if (! isset($candlesByRole[$role]) || ! $this->validSeries($candlesByRole[$role])) {
                return $this->emptyResult($profile, 'BACKTEST_INVALID_'.$role.'_SERIES');
            }
        }

        $entryCandles = array_values($candlesByRole['entry']);
        $minimumHistory = max(30, (int) ($assumptions['minimum_history'] ?? config('ict.minimum_candles', 30)));
        $maxLimitWaitBars = max(1, (int) ($assumptions['max_limit_wait_bars'] ?? 5));
        $maxHoldingBars = max(1, (int) ($assumptions['max_holding_bars'] ?? 100));
        $feeRate = max(0.0, (float) ($assumptions['fee_rate'] ?? config('analysis.fee_rate', 0.001)));
        $slippageBps = max(0.0, (float) ($assumptions['slippage_bps'] ?? 0.0));
        $trades = [];
        $rejected = 0;
        $unfilledLimits = 0;
        $index = $minimumHistory - 1;

        while ($index < count($entryCandles) - 1) {
            $decisionTime = (int) $entryCandles[$index]['close_time'];
            $history = [];
            $hasHistory = true;
            foreach (['context', 'structure', 'setup', 'entry'] as $role) {
                $history[$role] = array_values(array_filter(
                    $candlesByRole[$role],
                    fn (array $candle): bool => (int) $candle['close_time'] <= $decisionTime,
                ));
                if (count($history[$role]) < $minimumHistory) $hasHistory = false;
            }
            if (! $hasHistory) {
                $index++;
                continue;
            }

            $analysis = $this->engine->evaluate($history, $profile);
            $scenario = collect($analysis['scenarios'] ?? [])->first(fn (array $item): bool =>
                ($item['status'] ?? null) === 'ENTERABLE'
                && is_array($item['candidate'] ?? null)
            );
            $scenario ??= collect($analysis['scenarios'] ?? [])->first(fn (array $item): bool =>
                ($item['status'] ?? null) === 'WAIT'
                && is_array($item['candidate'] ?? null)
            );
            $candidate = $scenario['candidate'] ?? null;
            if ($candidate === null) {
                $index++;
                continue;
            }

            $marketReady = ($candidate['entry_plans']['market']['status'] ?? null) === 'PLANNED';
            $limitReady = ($candidate['entry_plans']['limit']['status'] ?? null) === 'PLANNED';
            $fill = null;
            if ($marketReady) {
                $next = $entryCandles[$index + 1];
                $rawEntry = (float) $next['open'];
                $fill = [
                    'type' => 'MARKET',
                    'index' => $index + 1,
                    'price' => $rawEntry * (1 + ($slippageBps / 10000)),
                    'assumption' => 'NEXT_BAR_OPEN_PLUS_CONFIGURED_SLIPPAGE',
                ];
            } elseif ($limitReady) {
                $limitPrice = (float) $candidate['entry_plans']['limit']['limit_price'];
                $lastWaitIndex = min(count($entryCandles) - 1, $index + $maxLimitWaitBars);
                for ($futureIndex = $index + 1; $futureIndex <= $lastWaitIndex; $futureIndex++) {
                    if ((float) $entryCandles[$futureIndex]['low'] <= $limitPrice) {
                        $fill = [
                            'type' => 'LIMIT',
                            'index' => $futureIndex,
                            'price' => $limitPrice,
                            'assumption' => 'OHLCV_LOW_TOUCH_ASSUMED_FILL_NOT_QUEUE_CONFIRMED',
                        ];
                        break;
                    }
                }
                if ($fill === null) {
                    $unfilledLimits++;
                    $index = $lastWaitIndex + 1;
                    continue;
                }
            } else {
                $rejected++;
                $index++;
                continue;
            }

            $entryPrice = (float) $fill['price'];
            $stop = (float) ($candidate['stop'] ?? 0);
            $target1 = (float) ($candidate['target1'] ?? 0);
            $target2 = (float) ($candidate['target2'] ?? 0);
            if ($stop <= 0 || $entryPrice <= $stop || $target1 <= $entryPrice || $target2 <= $target1) {
                $rejected++;
                $index = $fill['index'] + 1;
                continue;
            }

            $trade = $this->simulateTrade(
                $entryCandles,
                $fill,
                $stop,
                $target1,
                $target2,
                $maxHoldingBars,
                $feeRate,
                $slippageBps,
                (string) ($scenario['scenario'] ?? 'UNKNOWN'),
            );
            $trades[] = $trade;
            $index = max($index + 1, (int) $trade['exit_index'] + 1);
        }

        return [
            'engine' => 'ICT_OHLCV_HISTORICAL_REPLAY',
            'profile' => $profile,
            'data_source' => 'OHLCV_ONLY',
            'historical_order_book_used' => false,
            'assumptions' => [
                'fee_rate_per_side' => $feeRate,
                'slippage_bps_per_side' => $slippageBps,
                'max_limit_wait_bars' => $maxLimitWaitBars,
                'max_holding_bars' => $maxHoldingBars,
                'limit_fill_model' => 'LOW_TOUCH_ASSUMED_FILL_NOT_QUEUE_CONFIRMED',
                'same_bar_stop_target_policy' => 'STOP_FIRST_CONSERVATIVE',
                'partial_exit_policy' => '50_PERCENT_AT_TARGET_1_50_PERCENT_AT_TARGET_2_OR_FINAL_EXIT',
            ],
            'counts' => ['trades' => count($trades), 'unfilled_limits' => $unfilledLimits, 'rejected_candidates' => $rejected],
            'metrics' => $this->metrics($trades),
            'trades' => $trades,
        ];
    }

    private function simulateTrade(array $candles, array $fill, float $stop, float $target1, float $target2, int $maxHoldingBars, float $feeRate, float $slippageBps, string $scenario): array
    {
        $entry = (float) $fill['price'];
        $riskPerUnit = $entry - $stop;
        $firstExit = null;
        $secondExit = null;
        $firstExitIndex = null;
        $secondExitIndex = null;
        $target1Reached = false;
        $lastIndex = min(count($candles) - 1, (int) $fill['index'] + $maxHoldingBars);

        for ($i = (int) $fill['index']; $i <= $lastIndex; $i++) {
            $candle = $candles[$i];
            $low = (float) $candle['low'];
            $high = (float) $candle['high'];

            if ($low <= $stop) {
                $exitPrice = $stop * (1 - ($slippageBps / 10000));
                if (! $target1Reached) {
                    $firstExit = $secondExit = $exitPrice;
                    $firstExitIndex = $secondExitIndex = $i;
                } else {
                    $secondExit = $exitPrice;
                    $secondExitIndex = $i;
                }
                break;
            }

            if (! $target1Reached && $high >= $target1) {
                $target1Reached = true;
                $firstExit = $target1 * (1 - ($slippageBps / 10000));
                $firstExitIndex = $i;
            }
            if ($target1Reached && $high >= $target2) {
                $secondExit = $target2 * (1 - ($slippageBps / 10000));
                $secondExitIndex = $i;
                break;
            }
        }

        $exitIndex = $secondExitIndex ?? $lastIndex;
        $finalClose = (float) $candles[$exitIndex]['close'] * (1 - ($slippageBps / 10000));
        if ($firstExit === null) $firstExit = $finalClose;
        if ($secondExit === null) $secondExit = $finalClose;
        $grossPnlPerUnit = (($firstExit - $entry) * 0.5) + (($secondExit - $entry) * 0.5);
        $entryFee = $entry * $feeRate;
        $exitFee = (($firstExit + $secondExit) / 2) * $feeRate;
        $netPnlPerUnit = $grossPnlPerUnit - $entryFee - $exitFee;
        $netR = $riskPerUnit > 0 ? $netPnlPerUnit / $riskPerUnit : 0.0;

        return [
            'scenario' => $scenario,
            'entry_type' => $fill['type'],
            'fill_assumption' => $fill['assumption'],
            'entry_index' => (int) $fill['index'],
            'entry_time' => (int) $candles[$fill['index']]['close_time'],
            'entry_price' => $entry,
            'stop' => $stop,
            'target1' => $target1,
            'target2' => $target2,
            'target1_reached' => $target1Reached,
            'target1_exit' => $firstExit,
            'target2_or_final_exit' => $secondExit,
            'exit_index' => $exitIndex,
            'exit_time' => (int) $candles[$exitIndex]['close_time'],
            'gross_pnl_per_unit' => $grossPnlPerUnit,
            'fees_per_unit' => $entryFee + $exitFee,
            'net_pnl_per_unit' => $netPnlPerUnit,
            'net_r' => $netR,
            'outcome' => $netPnlPerUnit > 0 ? 'WIN' : ($netPnlPerUnit < 0 ? 'LOSS' : 'BREAKEVEN'),
        ];
    }

    private function metrics(array $trades): array
    {
        $count = count($trades);
        if ($count === 0) return ['trade_count' => 0, 'win_rate' => 0.0, 'expectancy_r' => 0.0, 'profit_factor' => 0.0, 'max_drawdown_per_unit' => 0.0, 'net_pnl_per_unit_sum' => 0.0];

        $wins = array_values(array_filter($trades, fn (array $trade): bool => $trade['net_pnl_per_unit'] > 0));
        $grossWins = array_sum(array_map(fn (array $trade): float => max(0.0, $trade['net_pnl_per_unit']), $trades));
        $grossLosses = abs(array_sum(array_map(fn (array $trade): float => min(0.0, $trade['net_pnl_per_unit']), $trades)));
        $equity = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;
        foreach ($trades as $trade) {
            $equity += (float) $trade['net_pnl_per_unit'];
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, $peak - $equity);
        }

        return [
            'trade_count' => $count,
            'win_rate' => count($wins) / $count,
            'expectancy_r' => array_sum(array_column($trades, 'net_r')) / $count,
            'profit_factor' => $grossLosses > 0 ? $grossWins / $grossLosses : ($grossWins > 0 ? null : 0.0),
            'max_drawdown_per_unit' => $maxDrawdown,
            'net_pnl_per_unit_sum' => array_sum(array_column($trades, 'net_pnl_per_unit')),
        ];
    }

    private function validSeries(array $candles): bool
    {
        if (count($candles) < max(30, (int) config('ict.minimum_candles', 30))) return false;
        $previous = -1;
        foreach ($candles as $candle) {
            foreach (['open', 'high', 'low', 'close', 'volume', 'close_time'] as $key) {
                if (! isset($candle[$key]) || ! is_numeric($candle[$key])) return false;
            }
            if ((int) $candle['close_time'] <= $previous
                || (float) $candle['high'] < max((float) $candle['open'], (float) $candle['close'])
                || (float) $candle['low'] > min((float) $candle['open'], (float) $candle['close'])) return false;
            $previous = (int) $candle['close_time'];
        }
        return true;
    }

    private function emptyResult(string $profile, string $reason): array
    {
        return [
            'engine' => 'ICT_OHLCV_HISTORICAL_REPLAY',
            'profile' => $profile,
            'data_source' => 'OHLCV_ONLY',
            'historical_order_book_used' => false,
            'counts' => ['trades' => 0, 'unfilled_limits' => 0, 'rejected_candidates' => 0],
            'metrics' => $this->metrics([]),
            'trades' => [],
            'reasons' => [$reason],
        ];
    }
}
