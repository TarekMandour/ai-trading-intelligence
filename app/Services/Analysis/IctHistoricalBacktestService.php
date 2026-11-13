<?php

namespace App\Services\Analysis;

/**
 * Chronological OHLCV-only replay. Fills and costs are explicit assumptions.
 * The engine is evaluated only with candles closed by the decision timestamp.
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
        $riskFraction = min(1.0, max(0.0001, (float) ($assumptions['risk_fraction'] ?? 0.01)));
        $initialCapital = max(0.01, (float) ($assumptions['initial_capital'] ?? 10000.0));
        $trades = [];
        $rejected = 0;
        $unfilledLimits = 0;
        $testStartIndex = max($minimumHistory - 1, (int) ($assumptions['test_start_index'] ?? ($minimumHistory - 1)));
        if ($testStartIndex >= count($entryCandles) - 1) {
            return $this->emptyResult($profile, 'BACKTEST_HOLDOUT_HAS_NO_FORWARD_BARS');
        }

        $index = $testStartIndex;
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
            $eligible = array_values(array_filter(
                $analysis['scenarios'] ?? [],
                fn (array $item): bool => in_array($item['status'] ?? null, ['ENTERABLE', 'WAIT'], true)
                    && is_array($item['candidate'] ?? null),
            ));
            if ($eligible === []) {
                $index++;
                continue;
            }

            $fills = [];
            foreach ($eligible as $scenario) {
                $candidate = $scenario['candidate'];
                $fill = $this->findFill($candidate, $entryCandles, $index, $maxLimitWaitBars, $slippageBps);
                if ($fill === null) {
                    if (($candidate['entry_plans']['limit']['status'] ?? null) === 'PLANNED') $unfilledLimits++;
                    continue;
                }

                $entryPrice = (float) $fill['price'];
                $signalEntry = (float) ($candidate['entry'] ?? 0.0);
                $signalStop = (float) ($candidate['stop'] ?? 0.0);
                $signalRisk = $signalEntry - $signalStop;
                if ($signalEntry <= 0 || $signalRisk <= 0 || $entryPrice <= 0) {
                    $rejected++;
                    continue;
                }

                // Preserve structural stop distance, then anchor the stop and
                // R-multiple targets to the actual simulated fill price.
                $stop = $entryPrice - $signalRisk;
                $target1R = max(0.01, (float) config('ict.target_1_r', 2.0));
                $target2R = max($target1R, (float) config('ict.target_2_r', 3.0));
                $target1 = $entryPrice + ($signalRisk * $target1R);
                $target2 = $entryPrice + ($signalRisk * $target2R);

                $fills[] = $this->simulateTrade(
                    $entryCandles, $fill, $stop, $target1, $target2,
                    $maxHoldingBars, $feeRate, $slippageBps,
                    (string) ($scenario['scenario'] ?? 'UNKNOWN'),
                    $initialCapital, $riskFraction,
                );
            }

            if ($fills !== []) {
                foreach ($fills as $trade) $trades[] = $trade;
                $index = max($index + 1, max(array_column($fills, 'exit_index')) + 1);
            } else {
                $index++;
            }
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
                'risk_fraction_of_equity' => $riskFraction,
                'initial_capital' => $initialCapital,
                'limit_fill_model' => 'LOW_TOUCH_ASSUMED_FILL_NOT_QUEUE_CONFIRMED',
                'same_bar_stop_target_policy' => 'STOP_FIRST_CONSERVATIVE',
                'partial_exit_policy' => '50_PERCENT_AT_TARGET_1_50_PERCENT_AT_TARGET_2_OR_FINAL_EXIT',
                'test_start_index' => $testStartIndex,
                'evaluation_window' => 'CHRONOLOGICAL_HOLDOUT_FROM_TEST_START_INDEX',
            ],
            'benchmark' => [
                'name' => 'BUY_AND_HOLD_ENTRY_TIMEFRAME',
                'return' => $this->buyAndHoldReturn($entryCandles, $testStartIndex),
                'start_index' => $testStartIndex,
                'end_index' => count($entryCandles) - 1,
            ],
            'counts' => ['trades' => count($trades), 'unfilled_limits' => $unfilledLimits, 'rejected_candidates' => $rejected],
            'metrics' => $this->metrics($trades, $initialCapital),
            'trades' => $trades,
        ];
    }

    private function findFill(array $candidate, array $candles, int $decisionIndex, int $maxLimitWaitBars, float $slippageBps): ?array
    {
        if (($candidate['entry_plans']['market']['status'] ?? null) === 'PLANNED') {
            $nextIndex = $decisionIndex + 1;
            $next = $candles[$nextIndex] ?? null;
            if ($next === null) return null;

            return [
                'type' => 'MARKET',
                'index' => $nextIndex,
                'price' => (float) $next['open'] * (1 + ($slippageBps / 10000)),
                'assumption' => 'NEXT_BAR_OPEN_PLUS_CONFIGURED_SLIPPAGE',
            ];
        }

        if (($candidate['entry_plans']['limit']['status'] ?? null) !== 'PLANNED') return null;
        $limitPrice = (float) ($candidate['entry_plans']['limit']['limit_price'] ?? 0);
        if ($limitPrice <= 0) return null;

        $lastWaitIndex = min(count($candles) - 1, $decisionIndex + $maxLimitWaitBars);
        for ($futureIndex = $decisionIndex + 1; $futureIndex <= $lastWaitIndex; $futureIndex++) {
            $bar = $candles[$futureIndex];
            if ((float) $bar['open'] <= $limitPrice) {
                return [
                    'type' => 'LIMIT',
                    'index' => $futureIndex,
                    'price' => (float) $bar['open'],
                    'assumption' => 'LIMIT_GAP_OPEN_BETTER_PRICE_ASSUMED',
                ];
            }
            if ((float) $bar['low'] <= $limitPrice) {
                return [
                    'type' => 'LIMIT',
                    'index' => $futureIndex,
                    'price' => $limitPrice,
                    'assumption' => 'OHLCV_LOW_TOUCH_ASSUMED_FILL_NOT_QUEUE_CONFIRMED',
                ];
            }
        }

        return null;
    }

    private function simulateTrade(array $candles, array $fill, float $stop, float $target1, float $target2, int $maxHoldingBars, float $feeRate, float $slippageBps, string $scenario, float $initialCapital = 10000.0, float $riskFraction = 0.01): array
    {
        $entry = (float) $fill['price'];
        $riskPerUnit = $entry - $stop;
        $lastIndex = min(count($candles) - 1, (int) $fill['index'] + $maxHoldingBars);
        $firstExit = null;
        $secondExit = null;
        $secondExitIndex = null;
        $target1Reached = false;

        for ($i = (int) $fill['index']; $i <= $lastIndex; $i++) {
            $candle = $candles[$i];
            $low = (float) $candle['low'];
            $high = (float) $candle['high'];

            if ($low <= $stop) {
                $rawStopExit = min($stop, (float) $candle['open']);
                $exitPrice = $rawStopExit * (1 - ($slippageBps / 10000));
                if (! $target1Reached) {
                    $firstExit = $secondExit = $exitPrice;
                } else {
                    $secondExit = $exitPrice;
                }
                $secondExitIndex = $i;
                break;
            }

            if (! $target1Reached && $high >= $target1) {
                $target1Reached = true;
                $firstExit = $target1 * (1 - ($slippageBps / 10000));
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
        $plannedRiskCash = $initialCapital * $riskFraction;
        $quantity = $riskPerUnit > 0 ? $plannedRiskCash / $riskPerUnit : 0.0;
        $netPnlCash = $netPnlPerUnit * $quantity;

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
            'risk_per_unit' => $riskPerUnit,
            'quantity' => $quantity,
            'planned_risk_cash' => $plannedRiskCash,
            'gross_pnl_per_unit' => $grossPnlPerUnit,
            'fees_per_unit' => $entryFee + $exitFee,
            'net_pnl_per_unit' => $netPnlPerUnit,
            'net_pnl_cash' => $netPnlCash,
            'net_r' => $netR,
            'return_on_initial_capital' => $initialCapital > 0 ? $netPnlCash / $initialCapital : 0.0,
            'outcome' => $netPnlPerUnit > 0 ? 'WIN' : ($netPnlPerUnit < 0 ? 'LOSS' : 'BREAKEVEN'),
        ];
    }

    private function buyAndHoldReturn(array $candles, int $startIndex): float
    {
        $start = $candles[$startIndex] ?? null;
        $end = $candles[array_key_last($candles)] ?? null;
        if ($start === null || $end === null || (float) $start['close'] <= 0) return 0.0;

        return ((float) $end['close'] / (float) $start['close']) - 1;
    }

    private function metrics(array $trades, float $initialCapital = 10000.0): array
    {
        $count = count($trades);
        if ($count === 0) return [
            'trade_count' => 0, 'win_rate' => 0.0, 'expectancy_r' => 0.0,
            'profit_factor' => 0.0, 'max_drawdown_cash' => 0.0,
            'net_pnl_cash' => 0.0, 'net_return' => 0.0,
        ];

        $wins = array_values(array_filter($trades, fn (array $trade): bool => $trade['net_pnl_per_unit'] > 0));
        $grossWins = array_sum(array_map(fn (array $trade): float => max(0.0, $trade['net_pnl_cash']), $trades));
        $grossLosses = abs(array_sum(array_map(fn (array $trade): float => min(0.0, $trade['net_pnl_cash']), $trades)));
        $equity = $initialCapital;
        $peak = $initialCapital;
        $maxDrawdown = 0.0;
        foreach ($trades as $trade) {
            $equity += (float) $trade['net_pnl_cash'];
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, $peak - $equity);
        }

        $netPnl = array_sum(array_column($trades, 'net_pnl_cash'));
        return [
            'trade_count' => $count,
            'win_rate' => count($wins) / $count,
            'expectancy_r' => array_sum(array_column($trades, 'net_r')) / $count,
            'profit_factor' => $grossLosses > 0 ? $grossWins / $grossLosses : ($grossWins > 0 ? null : 0.0),
            'max_drawdown_cash' => $maxDrawdown,
            'net_pnl_cash' => $netPnl,
            'net_return' => $initialCapital > 0 ? $netPnl / $initialCapital : 0.0,
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
