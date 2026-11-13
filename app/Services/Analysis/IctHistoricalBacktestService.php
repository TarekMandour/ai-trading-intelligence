<?php

namespace App\Services\Analysis;

/**
 * Chronological OHLCV-only replay. Fills and costs are explicit assumptions.
 * Each scenario family is replayed with isolated capital and its own equity curve.
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
        $tradesByScenario = [];
        $capitalByScenario = [];
        $nextEligibleIndex = [];
        $unfilledByScenario = [];
        $rejectedByScenario = [];
        $testStartIndex = max($minimumHistory - 1, (int) ($assumptions['test_start_index'] ?? ($minimumHistory - 1)));
        if ($testStartIndex >= count($entryCandles) - 1) {
            return $this->emptyResult($profile, 'BACKTEST_HOLDOUT_HAS_NO_FORWARD_BARS');
        }

        // Every scenario has its own replay state. A loss or open position in one
        // model family must not suppress or subsidize a different model family.
        for ($index = $testStartIndex; $index < count($entryCandles) - 1; $index++) {
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
            if (! $hasHistory) continue;

            $analysis = $this->engine->evaluate($history, $profile);
            $scenarioResults = $analysis['scenarios'] ?? [];
            if (($analysis['canonical_scenario']['status'] ?? null) === 'ENTERABLE'
                && is_array($analysis['canonical_scenario']['candidate'] ?? null)) {
                array_unshift($scenarioResults, $analysis['canonical_scenario']);
            }
            foreach ($scenarioResults as $scenario) {
                $scenarioKey = (string) ($scenario['scenario'] ?? 'UNKNOWN');
                if ($scenarioKey === 'UNKNOWN' || $index < ($nextEligibleIndex[$scenarioKey] ?? $testStartIndex)) continue;
                if (! in_array($scenario['status'] ?? null, ['ENTERABLE', 'WAIT'], true)
                    || ! is_array($scenario['candidate'] ?? null)) continue;

                $candidate = $scenario['candidate'];
                $fillResult = $this->findFill($candidate, $entryCandles, $index, $maxLimitWaitBars, $slippageBps);
                $fill = $fillResult['fill'];
                if ($fill === null) {
                    if (($candidate['entry_plans']['limit']['status'] ?? null) === 'PLANNED') {
                        $unfilledByScenario[$scenarioKey] = ($unfilledByScenario[$scenarioKey] ?? 0) + 1;
                    }
                    $nextEligibleIndex[$scenarioKey] = max($index + 1, (int) $fillResult['next_eligible_index']);
                    continue;
                }

                $entryPrice = (float) $fill['price'];
                $signalEntry = (float) ($candidate['entry'] ?? 0.0);
                $signalStop = (float) ($candidate['stop'] ?? 0.0);
                $signalRisk = $signalEntry - $signalStop;
                if ($signalEntry <= 0 || $signalRisk <= 0 || $entryPrice <= 0) {
                    $rejectedByScenario[$scenarioKey] = ($rejectedByScenario[$scenarioKey] ?? 0) + 1;
                    $nextEligibleIndex[$scenarioKey] = (int) $fill['index'] + 1;
                    continue;
                }

                // Keep the original structural stop distance, then translate
                // all price levels to the actual simulated fill price.
                $stop = $entryPrice - $signalRisk;
                // Preserve structure-derived target distances. Fall back to configured
                // R multiples only when the candidate did not supply valid targets.
                $signalTarget1 = (float) ($candidate['target1'] ?? 0.0);
                $signalTarget2 = (float) ($candidate['target2'] ?? 0.0);
                $target1Distance = $signalTarget1 > $signalEntry
                    ? $signalTarget1 - $signalEntry
                    : $signalRisk * max(0.01, (float) config('ict.target_1_r', 2.0));
                $target2Distance = $signalTarget2 > $signalTarget1 && $signalTarget1 > $signalEntry
                    ? $signalTarget2 - $signalEntry
                    : $signalRisk * max((float) config('ict.target_1_r', 2.0), (float) config('ict.target_2_r', 3.0));
                $target1 = $entryPrice + $target1Distance;
                $target2 = max($entryPrice + $target2Distance, $target1 + ($signalRisk * 0.5));
                $scenarioCapital = $capitalByScenario[$scenarioKey] ?? $initialCapital;
                $trade = $this->simulateTrade(
                    $entryCandles, $fill, $stop, $target1, $target2,
                    $maxHoldingBars, $feeRate, $slippageBps, $scenarioKey,
                    $scenarioCapital, $riskFraction,
                );

                $tradesByScenario[$scenarioKey][] = $trade;
                $capitalByScenario[$scenarioKey] = max(0.0, $scenarioCapital + $trade['net_pnl_cash']);
                $nextEligibleIndex[$scenarioKey] = (int) $trade['exit_index'] + 1;
            }
        }

        $scenarioMetrics = [];
        $scenarioCounts = [];
        $trades = [];
        $totalUnfilled = 0;
        $totalRejected = 0;
        foreach ($tradesByScenario as $scenarioKey => $scenarioTrades) {
            usort($scenarioTrades, fn (array $a, array $b): int => $a['entry_time'] <=> $b['entry_time']);
            $scenarioMetrics[$scenarioKey] = $this->metrics($scenarioTrades, $initialCapital);
            $scenarioCounts[$scenarioKey] = [
                'trades' => count($scenarioTrades),
                'unfilled_limits' => $unfilledByScenario[$scenarioKey] ?? 0,
                'rejected_candidates' => $rejectedByScenario[$scenarioKey] ?? 0,
            ];
            $totalUnfilled += $scenarioCounts[$scenarioKey]['unfilled_limits'];
            $totalRejected += $scenarioCounts[$scenarioKey]['rejected_candidates'];
            foreach ($scenarioTrades as $trade) $trades[] = $trade;
        }
        usort($trades, fn (array $a, array $b): int => $a['entry_time'] <=> $b['entry_time']);

        // Do not fabricate a portfolio equity curve by summing independent
        // scenario tests against the same initial capital.
        $metrics = count($scenarioMetrics) === 1 ? reset($scenarioMetrics) : null;

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
                'initial_capital_per_scenario' => $initialCapital,
                'scenario_capital_isolated' => true,
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
            'counts' => [
                'trades' => count($trades),
                'unfilled_limits' => $totalUnfilled,
                'rejected_candidates' => $totalRejected,
                'scenario_families' => count($scenarioMetrics),
            ],
            'scenario_counts' => $scenarioCounts,
            'scenario_metrics' => $scenarioMetrics,
            'metrics' => $metrics,
            'metrics_scope' => count($scenarioMetrics) === 0 ? 'NO_SCENARIO_TRADES' : (count($scenarioMetrics) > 1 ? 'PER_SCENARIO_ONLY_NO_CROSS_STRATEGY_PORTFOLIO' : 'SINGLE_SCENARIO'),
            'trades' => $trades,
        ];
    }

    /**
     * Chronological 60/20/20 train-validation-test reporting.
     * No parameters are fitted on these splits; this is a leakage-resistant
     * evaluation layout, not an optimizer or a profitability guarantee.
     */
    public function runChronologicalSplits(array $candlesByRole, string $profile, array $assumptions = []): array
    {
        foreach (['context', 'structure', 'setup', 'entry'] as $role) {
            if (! isset($candlesByRole[$role]) || ! $this->validSeries($candlesByRole[$role])) {
                return ['status' => 'REJECTED', 'reason' => 'BACKTEST_INVALID_'.$role.'_SERIES', 'splits' => []];
            }
        }

        $entry = array_values($candlesByRole['entry']);
        $count = count($entry);
        $minimumHistory = max(30, (int) ($assumptions['minimum_history'] ?? config('ict.minimum_candles', 30)));
        $minimumEvaluationBars = max(5, (int) ($assumptions['minimum_evaluation_bars'] ?? 20));
        $trainEnd = (int) floor($count * 0.60) - 1;
        $validationEnd = (int) floor($count * 0.80) - 1;
        $validationStart = $trainEnd + 1;
        $testStart = $validationEnd + 1;
        $trainEvaluationBars = $trainEnd - ($minimumHistory - 1);
        $validationEvaluationBars = $validationEnd - $validationStart;
        $testEvaluationBars = ($count - 1) - $testStart;
        if ($trainEvaluationBars < $minimumEvaluationBars
            || $validationEvaluationBars < $minimumEvaluationBars
            || $testEvaluationBars < $minimumEvaluationBars) {
            return [
                'status' => 'REJECTED', 'reason' => 'INSUFFICIENT_DATA_FOR_CHRONOLOGICAL_60_20_20_SPLITS',
                'entry_candles' => $count, 'minimum_history' => $minimumHistory,
                'minimum_evaluation_bars_per_split' => $minimumEvaluationBars,
                'available_evaluation_bars' => ['train' => $trainEvaluationBars, 'validation' => $validationEvaluationBars, 'test' => $testEvaluationBars],
                'splits' => [],
            ];
        }

        $trainSeries = $this->truncateRolesAt($candlesByRole, (int) $entry[$trainEnd]['close_time']);
        $validationSeries = $this->truncateRolesAt($candlesByRole, (int) $entry[$validationEnd]['close_time']);
        $train = $this->run($trainSeries, $profile, array_merge($assumptions, ['test_start_index' => $minimumHistory - 1]));
        $validation = $this->run($validationSeries, $profile, array_merge($assumptions, ['test_start_index' => $validationStart]));
        $test = $this->run($candlesByRole, $profile, array_merge($assumptions, ['test_start_index' => $testStart]));

        return [
            'engine' => 'ICT_CHRONOLOGICAL_60_20_20_REPLAY',
            'profile' => $profile,
            'data_source' => 'CALLER_SUPPLIED_COMPLETED_OHLCV',
            'minimum_evaluation_bars_per_split' => $minimumEvaluationBars,
            'split_indices' => [
                'train' => ['start' => $minimumHistory - 1, 'end' => $trainEnd],
                'validation' => ['start' => $validationStart, 'end' => $validationEnd],
                'test' => ['start' => $testStart, 'end' => $count - 1],
            ],
            'splits' => ['train' => $train, 'validation' => $validation, 'test' => $test],
            'promotion_status' => 'NOT_APPROVED_WITHOUT_PREDECLARED_THRESHOLDS_AND_PAPER_TRADING',
            'limitations' => [
                'No parameter fitting or optimization is performed.',
                'OHLCV limit touches are assumed fills, not queue-confirmed fills.',
                'No historical order-book depth or tick-level intrabar sequence is available.',
                'The test split must remain untouched until all hypotheses and thresholds are frozen.',
            ],
        ];
    }

    private function truncateRolesAt(array $candlesByRole, int $cutoff): array
    {
        $result = [];
        foreach (['context', 'structure', 'setup', 'entry'] as $role) {
            $result[$role] = array_values(array_filter(
                $candlesByRole[$role],
                fn (array $candle): bool => (int) $candle['close_time'] <= $cutoff,
            ));
        }
        return $result;
    }

    /**
     * @return array{fill: ?array, next_eligible_index: int}
     */
    private function findFill(array $candidate, array $candles, int $decisionIndex, int $maxLimitWaitBars, float $slippageBps): array
    {
        if (($candidate['entry_plans']['market']['status'] ?? null) === 'PLANNED') {
            $nextIndex = $decisionIndex + 1;
            $next = $candles[$nextIndex] ?? null;
            if ($next === null) return ['fill' => null, 'next_eligible_index' => $nextIndex];

            return [
                'fill' => [
                    'type' => 'MARKET',
                    'index' => $nextIndex,
                    'price' => (float) $next['open'] * (1 + ($slippageBps / 10000)),
                    'assumption' => 'NEXT_BAR_OPEN_PLUS_CONFIGURED_SLIPPAGE',
                ],
                'next_eligible_index' => $nextIndex,
            ];
        }

        if (($candidate['entry_plans']['limit']['status'] ?? null) !== 'PLANNED') {
            return ['fill' => null, 'next_eligible_index' => $decisionIndex + 1];
        }
        $limitPrice = (float) ($candidate['entry_plans']['limit']['limit_price'] ?? 0);
        if ($limitPrice <= 0) return ['fill' => null, 'next_eligible_index' => $decisionIndex + 1];

        $lastWaitIndex = min(count($candles) - 1, $decisionIndex + $maxLimitWaitBars);
        for ($futureIndex = $decisionIndex + 1; $futureIndex <= $lastWaitIndex; $futureIndex++) {
            $bar = $candles[$futureIndex];
            if ((float) $bar['open'] <= $limitPrice) {
                return [
                    'fill' => [
                        'type' => 'LIMIT',
                        'index' => $futureIndex,
                        'price' => (float) $bar['open'],
                        'assumption' => 'LIMIT_GAP_OPEN_BETTER_PRICE_ASSUMED',
                    ],
                    'next_eligible_index' => $lastWaitIndex + 1,
                ];
            }
            if ((float) $bar['low'] <= $limitPrice) {
                return [
                    'fill' => [
                        'type' => 'LIMIT',
                        'index' => $futureIndex,
                        'price' => $limitPrice,
                        'assumption' => 'OHLCV_LOW_TOUCH_ASSUMED_FILL_NOT_QUEUE_CONFIRMED',
                    ],
                    'next_eligible_index' => $lastWaitIndex + 1,
                ];
            }
        }

        return ['fill' => null, 'next_eligible_index' => $lastWaitIndex + 1];
    }

    private function simulateTrade(array $candles, array $fill, float $stop, float $target1, float $target2, int $maxHoldingBars, float $feeRate, float $slippageBps, string $scenario, float $capital = 10000.0, float $riskFraction = 0.01): array
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

            // Conservative OHLC convention: stop wins when both stop and
            // target can be touched inside one candle and tick order is unknown.
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
        $plannedRiskCash = $capital * $riskFraction;
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
            'return_on_initial_capital' => $capital > 0 ? $netPnlCash / $capital : 0.0,
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

    private function metrics(array $trades, float $initialCapital): array
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
            'counts' => ['trades' => 0, 'unfilled_limits' => 0, 'rejected_candidates' => 0, 'scenario_families' => 0],
            'scenario_counts' => [],
            'scenario_metrics' => [],
            'metrics' => [],
            'metrics_scope' => 'NO_TRADES',
            'trades' => [],
            'reasons' => [$reason],
        ];
    }
}
