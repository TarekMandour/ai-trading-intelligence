<?php

namespace App\Services\MarketData;

use App\Services\Analysis\TechnicalStrategyEngine;

/** Chronological OHLCV replay of the production technical strategy; stop wins same-bar ambiguity. */
class EgxTechnicalBacktestService
{
    public function __construct(
        private readonly EgxCandleSyncService $candles,
        private readonly TechnicalStrategyEngine $engine,
    ) {}

    public function run(string $assetId, string $profile, array $assumptions = []): array
    {
        $profileFrames = config('analysis.profiles', []);
        if (!isset($profileFrames[$profile])) return ['status' => 'REJECTED', 'market' => 'EGX', 'reason' => 'UNSUPPORTED_PROFILE'];
        if (!isset($assumptions['fee_rate'], $assumptions['slippage_bps']) || !is_numeric($assumptions['fee_rate']) || !is_numeric($assumptions['slippage_bps'])) {
            return ['status' => 'REJECTED', 'market' => 'EGX', 'reason' => 'EGX_EXECUTION_COST_ASSUMPTIONS_REQUIRED'];
        }

        $series = [];
        $counts = [];
        foreach ($profileFrames[$profile] as $role => $timeframe) {
            $rows = $this->candles->candles($assetId, $timeframe, (int) ($assumptions['candle_limit'] ?? 5000));
            $counts[$timeframe] = count($rows);
            if (count($rows) < 35) return [
                'status' => 'REJECTED', 'market' => 'EGX', 'asset_id' => $assetId, 'profile' => $profile,
                'reason' => 'INSUFFICIENT_HISTORICAL_CANDLES_'.$timeframe, 'candle_counts' => $counts,
            ];
            $series[$role] = array_map(static fn (array $c): array => [
                'open_time' => (int) $c['open_time'], 'close_time' => (int) $c['close_time'],
                'open' => (float) $c['open'], 'high' => (float) $c['high'], 'low' => (float) $c['low'],
                'close' => (float) $c['close'], 'volume' => (float) $c['volume'],
            ], $rows);
        }

        $entry = $series['entry'];
        $minimumHistory = max(30, (int) ($assumptions['warmup_bars'] ?? 30));
        $maxHolding = max(1, (int) ($assumptions['max_holding_bars'] ?? 30));
        $feeRate = max(0.0, (float) $assumptions['fee_rate']);
        $slippage = max(0.0, (float) $assumptions['slippage_bps']) / 10000;
        $initialCapital = max(0.01, (float) ($assumptions['initial_capital'] ?? 100000));
        $riskFraction = min(1.0, max(0.0001, (float) ($assumptions['risk_fraction'] ?? 0.01)));
        $trades = [];
        $equity = $initialCapital;
        $peak = 1.0;
        $maxDrawdown = 0.0;
        $lastExitIndex = -1;

        for ($i = $minimumHistory - 1; $i < count($entry) - 1; $i++) {
            if ($i <= $lastExitIndex) continue;
            $asOf = (int) $entry[$i]['close_time'];
            $snapshot = [];
            foreach ($series as $role => $rows) {
                $snapshot[$role] = array_values(array_filter($rows, static fn (array $c): bool => (int) $c['close_time'] <= $asOf));
                if (count($snapshot[$role]) < 30) continue 2;
            }
            $signal = $this->engine->evaluate($snapshot, $profile);
            if (($signal['decision'] ?? null) !== 'ENTERABLE' || !is_array($signal['candidate'] ?? null)) continue;

            $plan = $signal['candidate'];
            $stop = (float) ($plan['stop'] ?? 0);
            $target = (float) ($plan['target1'] ?? 0);
            $next = $entry[$i + 1];
            $fill = (float) $next['open'] * (1 + $slippage);
            if ($stop <= 0 || $target <= $fill || $stop >= $fill) continue;

            $exitPrice = (float) $entry[min(count($entry) - 1, $i + $maxHolding)]['close'];
            $exitIndex = min(count($entry) - 1, $i + $maxHolding);
            $exitReason = 'TIME_EXIT';
            for ($j = $i + 1; $j <= min(count($entry) - 1, $i + $maxHolding); $j++) {
                $bar = $entry[$j];
                if ((float) $bar['open'] <= $stop) { $exitPrice = (float) $bar['open'] * (1 - $slippage); $exitIndex = $j; $exitReason = 'GAP_STOP_LOSS'; break; }
                if ((float) $bar['low'] <= $stop) { $exitPrice = $stop * (1 - $slippage); $exitIndex = $j; $exitReason = 'STOP_LOSS'; break; }
                if ((float) $bar['high'] >= $target) { $exitPrice = $target * (1 - $slippage); $exitIndex = $j; $exitReason = 'TARGET_1'; break; }
            }
            $estimatedStopFill = $stop * (1 - $slippage);
            $estimatedRiskPerUnit = ($fill - $estimatedStopFill) + (($fill + $estimatedStopFill) * $feeRate);
            if ($estimatedRiskPerUnit <= 0) continue;
            $riskBudget = $equity * $riskFraction;
            $quantity = $riskBudget / $estimatedRiskPerUnit;
            $grossPnl = ($exitPrice - $fill) * $quantity;
            $fees = (($fill * $quantity) + ($exitPrice * $quantity)) * $feeRate;
            $netPnl = $grossPnl - $fees;
            $net = $equity > 0 ? $netPnl / $equity : 0;
            $equity += $netPnl;
            $peak = max($peak, $equity);
            $maxDrawdown = max($maxDrawdown, ($peak - $equity) / $peak);
            $trades[] = [
                'signal_time' => $asOf, 'entry_time' => (int) $next['open_time'], 'exit_time' => (int) $entry[$exitIndex]['close_time'],
                'entry' => round($fill, 6), 'stop' => $stop, 'target_1' => $target,
                'exit' => round($exitPrice, 6), 'exit_reason' => $exitReason,
                'quantity' => round($quantity, 6), 'risk_budget' => round($riskBudget, 2),
                'estimated_risk_per_unit' => round($estimatedRiskPerUnit, 6),
                'gross_pnl' => round($grossPnl, 2), 'fees' => round($fees, 2),
                'net_pnl' => round($netPnl, 2), 'equity_after' => round($equity, 2),
                'net_return_pct' => round($net * 100, 4), 'opportunity_score' => $signal['metrics']['opportunity_score'] ?? 0,
            ];
            $lastExitIndex = $exitIndex;
        }

        $wins = count(array_filter($trades, static fn (array $t): bool => $t['net_return_pct'] > 0));
        return [
            'status' => 'COMPLETED', 'engine' => 'TECHNICAL_STRATEGY_CHRONOLOGICAL_REPLAY',
            'market' => 'EGX', 'venue' => 'THNDR_HISTORICAL_CANDLES', 'asset_id' => $assetId, 'profile' => $profile,
            'timeframes' => $profileFrames[$profile], 'candle_counts' => $counts,
            'assumptions' => ['initial_capital' => $initialCapital, 'risk_fraction_per_trade' => $riskFraction, 'fee_rate_per_side' => $feeRate, 'slippage_bps_per_side' => $assumptions['slippage_bps'], 'warmup_bars' => $minimumHistory, 'same_bar_resolution' => 'STOP_FIRST', 'fill_model' => 'NEXT_ENTRY_CANDLE_OPEN'],
            'counts' => ['trades' => count($trades), 'wins' => $wins, 'losses' => count($trades) - $wins],
            'win_rate_pct' => count($trades) ? round($wins / count($trades) * 100, 2) : 0,
            'final_equity' => round($equity, 2),
            'net_return_pct' => round((($equity / $initialCapital) - 1) * 100, 2),
            'max_drawdown_pct' => round($maxDrawdown * 100, 2),
            'trades' => $trades,
            'limitations' => ['No corporate-action adjustment', 'No historical order-book depth', 'OHLCV fill simulation is not an exchange-confirmed fill'],
        ];
    }
}
