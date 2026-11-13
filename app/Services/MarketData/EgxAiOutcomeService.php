<?php

namespace App\Services\MarketData;

use App\Models\EgxScanRun;
use Illuminate\Support\Facades\DB;

class EgxAiOutcomeService
{
    private const ENTRY_FRAMES = ['Scalping' => '1m', 'Intraday' => '5m', 'Swing' => '1h'];

    /** Evaluate saved point-in-time AI reports only against candles opened after their as_of timestamp. */
    public function evaluate(?string $assetId = null, int $horizonBars = 20): array
    {
        $runs = EgxScanRun::query()->where('status', 'COMPLETED')->whereNotNull('results')->orderBy('id')->get();
        $evaluations = [];
        foreach ($runs as $run) {
            foreach ($run->results ?? [] as $stock) {
                if ($assetId !== null && ($stock['asset_id'] ?? null) !== $assetId) continue;
                foreach (($stock['profiles'] ?? []) as $profile => $result) {
                    $ai = $result['gemini_analysis'] ?? null;
                    if (! is_array($ai) || ($ai['status'] ?? null) !== 'COMPLETED') continue;
                    $snapshot = $ai['input_snapshot'] ?? [];
                    $asOf = $ai['as_of'] ?? $snapshot['as_of'] ?? null;
                    $frame = self::ENTRY_FRAMES[$profile] ?? null;
                    if (! $asOf || ! $frame) continue;
                    $afterMs = (int) (strtotime($asOf) * 1000);
                    $future = DB::table('egx_candles')->where('asset_id', $stock['asset_id'])
                        ->where('timeframe', $frame)->where('open_time', '>', $afterMs)
                        ->orderBy('open_time')->limit($horizonBars)->get();
                    if ($future->count() < $horizonBars) continue;

                    $report = $ai['report'] ?? [];
                    $decision = $report['decision'] ?? 'INSUFFICIENT_DATA';
                    $entry = $report['entry_zone']['low'] ?? null;
                    $entryHigh = $report['entry_zone']['high'] ?? null;
                    $stop = $report['stop_loss'] ?? null;
                    $target = $report['target_1'] ?? null;
                    $outcome = 'NO_TRADE';
                    $barsToOutcome = null;
                    if ($decision === 'ENTER_NOW' && is_numeric($entry) && is_numeric($entryHigh) && is_numeric($stop) && is_numeric($target)) {
                        foreach ($future as $index => $candle) {
                            $entryTouched = (float) $candle->low <= (float) $entryHigh && (float) $candle->high >= (float) $entry;
                            if (! $entryTouched) continue;
                            $stopHit = (float) $candle->low <= (float) $stop;
                            $targetHit = (float) $candle->high >= (float) $target;
                            if ($stopHit && $targetHit) { $outcome = 'AMBIGUOUS_SAME_BAR'; $barsToOutcome = $index + 1; break; }
                            if ($stopHit) { $outcome = 'STOP_LOSS'; $barsToOutcome = $index + 1; break; }
                            if ($targetHit) { $outcome = 'TARGET_1'; $barsToOutcome = $index + 1; break; }
                        }
                    }
                    $evaluations[] = [
                        'run_id' => $run->id, 'asset_id' => $stock['asset_id'], 'symbol' => $stock['symbol'],
                        'profile' => $profile, 'as_of' => $asOf, 'decision' => $decision,
                        'future_bars' => $horizonBars, 'outcome' => $outcome, 'bars_to_outcome' => $barsToOutcome,
                        'entry_zone' => $report['entry_zone'] ?? null, 'stop_loss' => $stop, 'target_1' => $target,
                    ];
                }
            }
        }
        $trades = array_values(array_filter($evaluations, fn ($row) => in_array($row['outcome'], ['TARGET_1','STOP_LOSS','AMBIGUOUS_SAME_BAR'], true)));
        $wins = count(array_filter($trades, fn ($row) => $row['outcome'] === 'TARGET_1'));
        $losses = count(array_filter($trades, fn ($row) => $row['outcome'] === 'STOP_LOSS'));
        return [
            'method' => 'FORWARD_ONLY_FIXED_HORIZON_OHLC',
            'horizon_bars' => $horizonBars,
            'evaluated_reports' => count($evaluations),
            'resolved_trades' => count($trades),
            'target_1_hits' => $wins,
            'stop_loss_hits' => $losses,
            'ambiguous_same_bar' => count($trades) - $wins - $losses,
            'target_hit_rate_excluding_ambiguous' => ($wins + $losses) > 0 ? round($wins / ($wins + $losses), 4) : null,
            'evaluations' => $evaluations,
            'limitations' => ['Only ENTER_NOW reports with complete entry/stop/target levels are classified as trades.', 'WATCHLIST and WAIT reports are retained as NO_TRADE, not counted as wins or losses.', 'Same-candle stop and target touches are ambiguous and excluded from hit rate.', 'No fees, slippage, spread, liquidity or partial fills are modeled.'],
        ];
    }
}
