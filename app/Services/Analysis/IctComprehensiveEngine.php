<?php

namespace App\Services\Analysis;

/**
 * Deterministic ICT research engine. Inputs must be completed Binance candles.
 * Thresholds are configurable research hypotheses, not calibrated probabilities.
 */
class IctComprehensiveEngine
{
    public function evaluate(array $candlesByRole, string $profile): array
    {
        foreach (['context', 'structure', 'setup', 'entry'] as $role) {
            if (! isset($candlesByRole[$role]) || ! $this->validSeries($candlesByRole[$role])) {
                return $this->unavailable($profile, 'ICT_DATA_INCOMPLETE_'.$role);
            }
        }

        $context = $candlesByRole['context'];
        $structure = $candlesByRole['structure'];
        $setup = $candlesByRole['setup'];
        $entry = $candlesByRole['entry'];
        // The causal pipeline is the authoritative market-reading layer.
        // Legacy scenario families remain in the response for comparison during
        // migration, but the top-level decision is derived from this pipeline.
        $pipeline = new IctAnalysisPipeline;
        $contextAnalysis = $pipeline->analyze($context);
        $structureAnalysis = $pipeline->analyze($structure);
        $setupAnalysis = $pipeline->analyze($setup, $entry);
        $contextEvidence = [
            'bias' => $contextAnalysis['market_structure']['bias'],
            'structure_bias' => $structureAnalysis['market_structure']['bias'],
            'dealing_range' => $contextAnalysis['dealing_range'],
            'last_structure_event' => $structureAnalysis['market_structure']['last_event'],
        ];
        $setupEvidence = $this->analyzeSetup($setup);
        $setupEvidence['causal_pipeline'] = $setupAnalysis;
        $lifecycle = new IctZoneLifecycleAnalyzer;
        $postSetupCandles = array_values(array_filter($setup, fn (array $c): bool => (int) ($c['close_time'] ?? 0) > (int) ($setupEvidence['fvg']['created_at'] ?? PHP_INT_MAX)));
        $fvgLifecycleCandles = array_merge($postSetupCandles, $entry);
        usort($fvgLifecycleCandles, fn (array $a, array $b): int => (int) $a['close_time'] <=> (int) $b['close_time']);
        $setupEvidence['fvg_lifecycle'] = $setupEvidence['fvg'] === null ? null : $lifecycle->bullishFvg($setupEvidence['fvg'], $fvgLifecycleCandles);
        $obCreatedAt = (int) ($setupEvidence['ob']['created_at'] ?? PHP_INT_MAX);
        $obLifecycleCandles = array_merge(array_values(array_filter($setup, fn (array $c): bool => (int) ($c['close_time'] ?? 0) > $obCreatedAt)), $entry);
        usort($obLifecycleCandles, fn (array $a, array $b): int => (int) $a['close_time'] <=> (int) $b['close_time']);
        $setupEvidence['ob_lifecycle'] = $setupEvidence['ob'] === null ? null : $lifecycle->bullishOrderBlock($setupEvidence['ob'], $obLifecycleCandles);
        $advancedEvidence = (new IctAdvancedEvidenceAnalyzer)->analyze($setup, $candlesByRole['reference'] ?? []);
        if (is_array($advancedEvidence['ote'] ?? null)) {
            $advancedEvidence['ote']['entry_in_zone'] = $entry[array_key_last($entry)]['close'] >= $advancedEvidence['ote']['low']
                && $entry[array_key_last($entry)]['close'] <= $advancedEvidence['ote']['high'];
        }
        $entryEvidence = [
            'bullish_trigger' => $this->bullishTrigger($entry),
            'last_close' => (float) $entry[array_key_last($entry)]['close'],
            'last_close_time' => (int) ($entry[array_key_last($entry)]['close_time'] ?? 0),
            'close_times' => array_map('intval', array_column($entry, 'close_time')),
        ];

        $results = [
            $this->sweepReversal($profile, $contextEvidence, $setupEvidence, $entryEvidence),
            $this->fvgContinuation($profile, $contextEvidence, $setupEvidence, $entryEvidence),
            $this->orderBlockReaction($profile, $contextEvidence, $setupEvidence, $entryEvidence),
            $this->breakerBlockReaction($profile, $contextEvidence, $advancedEvidence, $entryEvidence),
            $this->rejectionBlockReaction($profile, $contextEvidence, $setupEvidence, $advancedEvidence, $entryEvidence),
            $this->silverBullet($profile, $contextEvidence, $setupEvidence, $advancedEvidence, $entryEvidence),
            $this->judasSwing($profile, $contextEvidence, $setupEvidence, $advancedEvidence, $entryEvidence),
        ];

        $legacyWatchlist = (new IctOpportunityWatchlist)->evaluate($contextEvidence, $setupEvidence, $advancedEvidence, $entryEvidence);
        $tradePlan = $setupAnalysis['trade_plan'];
        $watchlist = $this->structuralWatchlist($setupAnalysis, (float) $entryEvidence['last_close'], $contextEvidence);
        $canonicalScenario = $this->canonicalScenario($profile, $tradePlan);
        $decision = $tradePlan['status'] === 'ENTERABLE'
            ? 'ENTERABLE'
            : ($watchlist !== null ? 'WATCHLIST' : (($tradePlan['reason'] ?? null) === 'WAITING_FOR_ZONE_RETEST' ? 'WAIT' : 'NO_OPPORTUNITY'));

        return [
            'engine' => 'ICT_COMPREHENSIVE',
            'version' => 'ict-v2-causal',
            'profile' => $profile,
            'direction' => 'LONG',
            'context' => $contextEvidence,
            'setup_evidence' => $setupEvidence,
            'advanced_evidence' => $advancedEvidence,
            'entry_evidence' => $entryEvidence,
            'structural_analysis' => $setupAnalysis,
            'structural_trade_plan' => $tradePlan,
            'canonical_scenario' => $canonicalScenario,
            'scenarios' => $results,
            'watchlist' => $watchlist,
            'legacy_watchlist_diagnostic' => $legacyWatchlist,
            'decision' => $decision,
        ];
    }

    private function canonicalScenario(string $profile, array $plan): array
    {
        $candidate = null;
        if (($plan['status'] ?? null) === 'ENTERABLE') {
            $candidate = [
                'entry' => $plan['entry'],
                'stop' => $plan['stop_loss'],
                'target1' => $plan['target1'],
                'target2' => $plan['target2'],
                'risk_reward' => $plan['target1_r'],
                'entry_zone' => $plan['entry_zone'],
                'entry_plans' => $plan['entry_plans'],
                'target_source' => $plan['target_source'],
                'invalidation' => $plan['invalidation'],
            ];
        }

        return [
            'scenario' => 'ICT_CAUSAL_STRUCTURE_LIQUIDITY',
            'profile' => $profile,
            'direction' => 'LONG',
            'status' => $plan['status'] ?? 'WAIT',
            'reasons' => isset($plan['reason']) ? [$plan['reason']] : ['CAUSAL_ICT_EVIDENCE_CONFIRMED'],
            'candidate' => $candidate,
            'evidence' => ['zone' => $plan['entry_zone'] ?? $plan['watchlist_zone'] ?? null],
        ];
    }

    private function structuralWatchlist(array $analysis, float $price, array $contextEvidence): ?array
    {
        $zone = $analysis['zones']['best_bullish_zone'] ?? null;
        if (! is_array($zone) || $price <= 0) return null;
        $structure = $analysis['market_structure'];
        $bias = $structure['bias'] ?? 'UNCERTAIN';
        $lastEvent = $structure['last_event']['type'] ?? null;
        if ($bias !== 'BULLISH' && $lastEvent !== 'CHOCH_BULLISH' && $lastEvent !== 'BOS_BULLISH') return null;
        $low = (float) $zone['low'];
        $high = (float) $zone['high'];
        $distance = $price < $low ? ($low - $price) / $price * 100
            : ($price > $high ? ($price - $high) / $price * 100 : 0.0);
        if ($distance > max(0.1, (float) config('ict.watchlist_max_zone_distance_pct', 3.0))) return null;

        $contextBias = $contextEvidence['bias'] ?? 'UNCERTAIN';
        $alignment = $contextBias === 'BEARISH' ? 'COUNTERTREND' : 'SUPPORTIVE_OR_NEUTRAL';

        return [
            'status' => 'WATCHLIST',
            'direction' => 'LONG',
            'reference_price' => $price,
            'zone_type' => $zone['type'],
            'zone' => ['low' => $low, 'high' => $high, 'ce' => (float) $zone['ce']],
            'distance_to_zone_pct' => round($distance, 3),
            'structure' => ['context_bias' => $contextBias, 'context_alignment' => $alignment, 'structure_bias' => $bias, 'mss_confirmed' => $lastEvent === 'CHOCH_BULLISH'],
            'reasons' => array_values(array_filter(['CAUSAL_BULLISH_STRUCTURE', 'QUALIFIED_ACTIVE_ZONE', 'WAITING_FOR_ENTRY_TIMEFRAME_CONFIRMATION', $alignment === 'COUNTERTREND' ? 'COUNTERTREND_SETUP_AGAINST_HIGHER_TIMEFRAME' : null])),
            'required_confirmation' => ['completed_bullish_entry_candle', 'close_above_previous_entry_high', 'zone_holds_without_invalidation'],
            'invalidation' => 'A completed close below the selected bullish zone low invalidates this watchlist setup.',
            'entry_authorized' => false,
        ];
    }

    private function analyzeSetup(array $candles): array
    {
        $swings = $this->swings($candles);
        $liquidity = $this->liquidity($candles, $swings);
        $displacement = $this->displacement($candles);
        $mss = $this->bullishMss($candles, $swings);
        $fvg = $this->bullishFvg($candles);
        $sequence = (new IctBullishSequenceAnalyzer)->detect($candles);
        if (($sequence['status'] ?? null) === 'CONFIRMED') {
            $liquidity['sweep_confirmed'] = true;
            $liquidity['sweep_low'] = $sequence['sweep']['low'];
            // Keep all displacement fields tied to the same historical event.
            // Do not combine a confirmed old displacement with the latest candle's
            // body_ratio/ATR, which may have the opposite direction.
            $displacement = array_merge($displacement, $sequence['displacement'], [
                'bullish' => true,
                'source' => 'CONFIRMED_BULLISH_SEQUENCE',
            ]);
            $mss['confirmed'] = true;
            $mss['level'] = $sequence['mss']['level'];
            $mss['index'] = $sequence['mss']['index'];
            $mss['close_time'] = $sequence['mss']['close_time'];
            $fvg = $sequence['fvg'];
        }
        // An order block is anchored to the candle immediately preceding the
        // confirmed displacement, not an arbitrary bearish candle near the
        // latest bar. Without a confirmed impulse there is no ICT order block.
        $ob = ($sequence['status'] ?? null) === 'CONFIRMED'
            ? $this->bullishOrderBlock($candles, (int) $sequence['displacement']['index'])
            : null;
        return compact('swings', 'liquidity', 'displacement', 'mss', 'fvg', 'ob', 'sequence');
    }

    private function sweepReversal(string $profile, array $context, array $setup, array $entry): array
    {
        $sequence = $setup['sequence'] ?? [];
        $sequenceConfirmed = ($sequence['status'] ?? null) === 'CONFIRMED';
        $sweep = $sequenceConfirmed;
        $displacement = $sequenceConfirmed;
        $mss = $sequenceConfirmed;
        $fvg = $sequence['fvg'] ?? null;
        $fvgLifecycle = $setup['fvg_lifecycle'] ?? null;
        if (($fvgLifecycle['status'] ?? null) === 'INVALIDATED' || ($fvgLifecycle['status'] ?? null) === 'FULLY_FILLED') $fvg = null;
        $fvgTouched = $fvg !== null && ($fvgLifecycle['touch_count'] ?? 0) > 0;
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $trigger = $this->triggerAfterZoneTouch($entry, $fvgLifecycle);
        $reasons = [];
        if (! $contextOk) $reasons[] = 'ICT01_BULLISH_CONTEXT_MISSING';
        if (! $sweep) $reasons[] = 'ICT01_SWEEP_NOT_CONFIRMED';
        if (! $displacement) $reasons[] = 'ICT01_NO_BULLISH_DISPLACEMENT';
        if (! $mss) $reasons[] = 'ICT01_NO_BULLISH_MSS';
        if ($fvg === null) $reasons[] = 'ICT01_NO_VALID_FVG';
        if (! $fvgTouched) $reasons[] = 'ICT01_NO_FVG_RETRACEMENT';
        if (! $trigger) $reasons[] = 'ICT01_NO_ENTRY_TRIGGER';
        return $this->scenario('ICT_01_SELL_SIDE_LIQUIDITY_SWEEP_REVERSAL', $profile, $contextOk && $sweep && $displacement && $mss && $fvg !== null && $fvgTouched && $trigger, $reasons, $entry['last_close'], $sequence['sweep']['low'] ?? null, $fvg);
    }

    private function fvgContinuation(string $profile, array $context, array $setup, array $entry): array
    {
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $sequenceConfirmed = ($setup['sequence']['status'] ?? null) === 'CONFIRMED';
        $displacement = $sequenceConfirmed && ($setup['displacement']['bullish'] ?? false);
        $fvg = $sequenceConfirmed ? $setup['fvg'] : null;
        $fvgLifecycle = $setup['fvg_lifecycle'] ?? null;
        if (($fvgLifecycle['status'] ?? null) === 'INVALIDATED' || ($fvgLifecycle['status'] ?? null) === 'FULLY_FILLED') $fvg = null;
        $touched = $fvg !== null && ($fvgLifecycle['touch_count'] ?? 0) > 0;
        $trigger = $this->triggerAfterZoneTouch($entry, $fvgLifecycle);
        $reasons = [];
        if (! $contextOk) $reasons[] = 'ICT02_NO_BULLISH_CONTEXT';
        if (! $displacement) $reasons[] = 'ICT02_NO_CONFIRMED_SWEEP_DISPLACEMENT_MSS_SEQUENCE';
        if ($fvg === null) $reasons[] = 'ICT02_NO_VALID_FVG';
        if (! $touched) $reasons[] = 'ICT02_NO_RETRACEMENT';
        if (! $trigger) $reasons[] = 'ICT02_NO_ENTRY_TRIGGER';
        return $this->scenario('ICT_02_BULLISH_FVG_CONTINUATION', $profile, $contextOk && $displacement && $fvg !== null && $touched && $trigger, $reasons, $entry['last_close'], $fvg['low'] ?? null, $fvg);
    }

    private function orderBlockReaction(string $profile, array $context, array $setup, array $entry): array
    {
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $ob = $setup['ob'];
        $obLifecycle = $setup['ob_lifecycle'] ?? null;
        if (($obLifecycle['status'] ?? null) === 'INVALIDATED') $ob = null;
        $touched = $ob !== null && ($obLifecycle['touch_count'] ?? 0) > 0;
        $trigger = $this->triggerAfterZoneTouch($entry, $obLifecycle);
        $reasons = [];
        if (! $contextOk) $reasons[] = 'ICT03_NO_BULLISH_CONTEXT';
        if ($ob === null) $reasons[] = 'ICT03_NO_VALID_ORDER_BLOCK';
        if (! $touched) $reasons[] = 'ICT03_NO_RETEST';
        if (! $trigger) $reasons[] = 'ICT03_NO_BULLISH_REACTION';
        return $this->scenario('ICT_03_BULLISH_ORDER_BLOCK_REACTION', $profile, $contextOk && $ob !== null && $touched && $trigger, $reasons, $entry['last_close'], $ob['low'] ?? null, $ob);
    }

    private function breakerBlockReaction(string $profile, array $context, array $advanced, array $entry): array
    {
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $breaker = $advanced['breaker_block'] ?? null;
        $trigger = $entry['bullish_trigger'];
        $reasons = [];
        if (! $contextOk) $reasons[] = 'ICT04_NO_BULLISH_CONTEXT';
        if ($breaker === null) $reasons[] = 'ICT04_NO_CONFIRMED_BREAKER_RETEST';
        if (! $trigger) $reasons[] = 'ICT04_NO_BULLISH_ENTRY_TRIGGER';

        return $this->scenario(
            'ICT_04_BULLISH_BREAKER_BLOCK_REACTION',
            $profile,
            $contextOk && $breaker !== null && $trigger,
            $reasons,
            $entry['last_close'],
            $breaker['low'] ?? null,
            $breaker,
        );
    }

    private function rejectionBlockReaction(string $profile, array $context, array $setup, array $advanced, array $entry): array
    {
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $rejection = $advanced['rejection_block'] ?? null;
        $structureConfirmed = ($setup['sequence']['status'] ?? null) === 'CONFIRMED'
            && ($setup['displacement']['bullish'] ?? false)
            && ($setup['mss']['confirmed'] ?? false);
        $trigger = $entry['bullish_trigger'];
        $reasons = [];
        if (! $contextOk) $reasons[] = 'ICT05_NO_BULLISH_CONTEXT';
        if ($rejection === null) $reasons[] = 'ICT05_NO_REJECTION_BLOCK_CANDIDATE';
        if (! $structureConfirmed) $reasons[] = 'ICT05_NO_DISPLACEMENT_MSS_CONFIRMATION';
        if (! $trigger) $reasons[] = 'ICT05_NO_BULLISH_ENTRY_TRIGGER';

        return $this->scenario(
            'ICT_05_BULLISH_REJECTION_BLOCK_REACTION',
            $profile,
            $contextOk && $rejection !== null && $structureConfirmed && $trigger,
            $reasons,
            $entry['last_close'],
            $rejection['low'] ?? null,
            $rejection,
        );
    }

    private function silverBullet(string $profile, array $context, array $setup, array $advanced, array $entry): array
    {
        $activeWindow = $this->hasActiveSessionModel($advanced, 'SILVER_BULLET');
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $fvg = $setup['fvg'];
        $lifecycle = $setup['fvg_lifecycle'] ?? null;
        if (in_array($lifecycle['status'] ?? null, ['FULLY_FILLED', 'INVALIDATED'], true)) $fvg = null;
        $retraced = $fvg !== null && ($lifecycle['touch_count'] ?? 0) > 0;
        $structure = ($setup['displacement']['bullish'] ?? false) && ($setup['mss']['confirmed'] ?? false);
        $trigger = $this->triggerAfterZoneTouch($entry, $lifecycle);
        $reasons = [];
        if (! $activeWindow) $reasons[] = 'ICT06_OUTSIDE_OR_UNCONFIGURED_SILVER_BULLET_WINDOW';
        if (! $contextOk) $reasons[] = 'ICT06_NO_BULLISH_CONTEXT';
        if (! $structure) $reasons[] = 'ICT06_NO_DISPLACEMENT_MSS';
        if (! $retraced) $reasons[] = 'ICT06_NO_VALID_FVG_RETRACEMENT';
        if (! $trigger) $reasons[] = 'ICT06_NO_BULLISH_ENTRY_TRIGGER';

        return $this->scenario(
            'ICT_06_SILVER_BULLET_LONG',
            $profile,
            $activeWindow && $contextOk && $structure && $retraced && $trigger,
            $reasons,
            $entry['last_close'],
            $fvg['low'] ?? null,
            $fvg,
        );
    }

    private function judasSwing(string $profile, array $context, array $setup, array $advanced, array $entry): array
    {
        $activeWindow = $this->hasActiveSessionModel($advanced, 'JUDAS');
        $contextOk = $context['bias'] === 'BULLISH' && $context['structure_bias'] !== 'BEARISH';
        $sequence = $setup['sequence'] ?? [];
        $confirmed = ($sequence['status'] ?? null) === 'CONFIRMED';
        $fvg = $sequence['fvg'] ?? null;
        $lifecycle = $setup['fvg_lifecycle'] ?? null;
        $retraced = $fvg !== null && ($lifecycle['touch_count'] ?? 0) > 0
            && ! in_array($lifecycle['status'] ?? null, ['FULLY_FILLED', 'INVALIDATED'], true);
        $trigger = $this->triggerAfterZoneTouch($entry, $lifecycle);
        $reasons = [];
        if (! $activeWindow) $reasons[] = 'ICT07_OUTSIDE_OR_UNCONFIGURED_JUDAS_WINDOW';
        if (! $contextOk) $reasons[] = 'ICT07_NO_BULLISH_CONTEXT';
        if (! $confirmed) $reasons[] = 'ICT07_NO_ORDERED_SWEEP_MSS_FVG_SEQUENCE';
        if (! $retraced) $reasons[] = 'ICT07_NO_VALID_FVG_RETRACEMENT';
        if (! $trigger) $reasons[] = 'ICT07_NO_BULLISH_ENTRY_TRIGGER';

        return $this->scenario(
            'ICT_07_JUDAS_SWING_LONG',
            $profile,
            $activeWindow && $contextOk && $confirmed && $retraced && $trigger,
            $reasons,
            $entry['last_close'],
            $fvg['low'] ?? null,
            $fvg,
        );
    }

    private function hasActiveSessionModel(array $advanced, string $prefix): bool
    {
        foreach (($advanced['session_models']['models'] ?? []) as $model) {
            if (str_contains(strtoupper((string) $model), $prefix)) return true;
        }

        return false;
    }

    private function scenario(string $key, string $profile, bool $confirmed, array $reasons, float $entry, ?float $stop, ?array $zone): array
    {
        if (! $confirmed) {
            // WAIT is reserved for a structurally valid setup that is only waiting
            // for its retracement/reaction/entry trigger. Missing core ICT evidence
            // means there is no opportunity yet, not an actionable waiting setup.
            $waitableReason = static fn (string $reason): bool => str_ends_with($reason, '_NO_FVG_RETRACEMENT')
                || str_ends_with($reason, '_NO_RETRACEMENT')
                || str_ends_with($reason, '_NO_RETEST')
                || str_ends_with($reason, '_NO_ENTRY_TRIGGER')
                || str_ends_with($reason, '_NO_BULLISH_REACTION')
                || str_ends_with($reason, '_NO_BULLISH_ENTRY_TRIGGER')
                || str_ends_with($reason, '_NO_VALID_FVG_RETRACEMENT');
            $hasCoreBlocker = collect($reasons)->contains(fn (string $reason): bool => ! $waitableReason($reason));
            $status = $hasCoreBlocker ? 'NO_OPPORTUNITY' : 'WAIT';

            return [
                'scenario' => $key, 'profile' => $profile, 'direction' => 'LONG',
                'status' => $status, 'reasons' => $reasons, 'candidate' => null,
                'evidence' => ['zone' => $zone],
            ];
        }
        if ($stop === null || $stop <= 0 || $entry <= $stop) return ['scenario' => $key, 'profile' => $profile, 'direction' => 'LONG', 'status' => 'REJECTED', 'reasons' => ['ICT_INVALID_STOP_GEOMETRY'], 'candidate' => null, 'evidence' => ['zone' => $zone]];
        $risk = $entry - $stop;
        return [
            'scenario' => $key, 'profile' => $profile, 'direction' => 'LONG', 'status' => 'ENTERABLE',
            'reasons' => ['ICT_REQUIRED_EVIDENCE_CONFIRMED'],
            'candidate' => [
                'entry' => $entry, 'stop' => $stop,
                'target1' => $entry + $risk * (float) config('ict.target_1_r', 2.0),
                'target2' => $entry + $risk * (float) config('ict.target_2_r', 3.0),
                'risk_reward' => (float) config('ict.target_1_r', 2.0),
                'entry_type' => 'MARKET_OR_LIMIT', 'entry_zone' => $zone,
                'entry_plans' => (new IctEntryPlanner)->plan($entry, $zone, true, $stop),
            ],
            'evidence' => ['zone' => $zone],
        ];
    }

    private function selectDecision(array $results, ?array $watchlist = null): string
    {
        if (collect($results)->contains(fn (array $r): bool => $r['status'] === 'ENTERABLE')) return 'ENTERABLE';
        // An early opportunity deserves its own state even when strict entry
        // scenarios are still waiting for a retest or trigger.
        if ($watchlist !== null) return 'WATCHLIST';
        if (collect($results)->contains(fn (array $r): bool => $r['status'] === 'WAIT')) return 'WAIT';
        return 'NO_OPPORTUNITY';
    }

    private function structureBias(array $candles): string
    {
        $swings = $this->swings($candles);
        if (count($swings['highs']) < 2 || count($swings['lows']) < 2) return 'UNCERTAIN';
        $h = array_slice($swings['highs'], -2);
        $l = array_slice($swings['lows'], -2);
        if ($h[1]['price'] > $h[0]['price'] && $l[1]['price'] > $l[0]['price']) return 'BULLISH';
        if ($h[1]['price'] < $h[0]['price'] && $l[1]['price'] < $l[0]['price']) return 'BEARISH';
        return 'RANGING';
    }

    private function swings(array $candles): array
    {
        $left = max(1, (int) config('ict.swing_left', 2));
        $right = max(1, (int) config('ict.swing_right', 2));
        $highs = $lows = [];
        for ($i = $left; $i < count($candles) - $right; $i++) {
            $window = array_slice($candles, $i - $left, $left + $right + 1);
            $otherHighs = array_column(array_merge(array_slice($window, 0, $left), array_slice($window, $left + 1)), 'high');
            $otherLows = array_column(array_merge(array_slice($window, 0, $left), array_slice($window, $left + 1)), 'low');
            if ((float) $candles[$i]['high'] > max($otherHighs)) $highs[] = ['index' => $i, 'price' => (float) $candles[$i]['high'], 'confirmation_index' => $i + $right];
            if ((float) $candles[$i]['low'] < min($otherLows)) $lows[] = ['index' => $i, 'price' => (float) $candles[$i]['low'], 'confirmation_index' => $i + $right];
        }
        return compact('highs', 'lows');
    }

    private function liquidity(array $candles, array $swings): array
    {
        $lows = $swings['lows'];
        $highs = $swings['highs'];
        $ssl = $lows === [] ? null : $lows[array_key_last($lows)]['price'];
        $bsl = $highs === [] ? null : $highs[array_key_last($highs)]['price'];
        $last = $candles[array_key_last($candles)];
        $reclaim = $ssl !== null && (float) $last['low'] < $ssl && (float) $last['close'] > $ssl;
        return ['ssl' => $ssl, 'bsl' => $bsl, 'sweep_confirmed' => $reclaim, 'sweep_low' => $reclaim ? (float) $last['low'] : null];
    }

    private function displacement(array $candles): array
    {
        if (count($candles) < 15) return ['bullish' => false];
        $last = $candles[array_key_last($candles)];
        $ranges = array_map(fn ($c) => max(0.0, (float) $c['high'] - (float) $c['low']), array_slice($candles, -15, 14));
        $atr = array_sum($ranges) / max(1, count($ranges));
        $range = (float) $last['high'] - (float) $last['low'];
        $body = (float) $last['close'] - (float) $last['open'];
        $ratio = $range > 0 ? $body / $range : 0;
        return ['bullish' => $body > 0 && $atr > 0 && $range >= $atr * (float) config('ict.displacement_atr_multiple', 1.2) && $ratio >= (float) config('ict.displacement_body_ratio', .65), 'atr' => $atr, 'body_ratio' => $ratio];
    }

    private function bullishMss(array $candles, array $swings): array
    {
        $highs = $swings['highs'];
        if ($highs === []) return ['confirmed' => false, 'level' => null];
        $level = $highs[array_key_last($highs)];
        $last = $candles[array_key_last($candles)];
        return ['confirmed' => $level['confirmation_index'] < count($candles) - 1 && (float) $last['close'] > $level['price'], 'level' => $level['price']];
    }

    private function bullishFvg(array $candles): ?array
    {
        if (count($candles) < 3) return null;
        $lookback = max(3, (int) config('ict.fvg_lookback', 30));
        $start = max(2, count($candles) - $lookback);
        for ($i = count($candles) - 1; $i >= $start; $i--) {
            $first = $candles[$i - 2];
            $third = $candles[$i];
            $low = (float) $first['high'];
            $high = (float) $third['low'];
            if ($high > $low) {
                return [
                    'low' => $low,
                    'high' => $high,
                    'ce' => ($low + $high) / 2,
                    'created_at' => $third['close_time'] ?? null,
                    'source_indices' => [$i - 2, $i - 1, $i],
                    'status' => 'ACTIVE',
                ];
            }
        }
        return null;
    }

    private function bullishOrderBlock(array $candles, int $displacementIndex): ?array
    {
        if ($displacementIndex < 1 || ! isset($candles[$displacementIndex])) return null;

        // Use the last bearish candle before the actual displacement candle.
        // Limit the search to five preceding candles to avoid assigning an
        // unrelated, stale down candle as the origin of the impulse.
        for ($i = $displacementIndex - 1; $i >= max(0, $displacementIndex - 5); $i--) {
            if ((float) $candles[$i]['close'] < (float) $candles[$i]['open']) {
                return [
                    'low' => (float) $candles[$i]['low'],
                    'high' => (float) $candles[$i]['open'],
                    'source_index' => $i,
                    'source_close_time' => (int) ($candles[$i]['close_time'] ?? 0),
                    // The zone only becomes actionable after the impulse closes.
                    'created_at' => (int) ($candles[$displacementIndex]['close_time'] ?? 0),
                    'status' => 'ACTIVE',
                ];
            }
        }

        return null;
    }

    private function triggerAfterZoneTouch(array $entryCandles, ?array $lifecycle): bool
    {
        if (! ($entryCandles['bullish_trigger'] ?? false)) return false;

        $touchTime = (int) ($lifecycle['last_touch_close_time'] ?? 0);
        if ($touchTime <= 0) return false;

        $barsAfterTouch = count(array_filter(
            $entryCandles['close_times'] ?? [],
            fn (int $closeTime): bool => $closeTime > $touchTime,
        ));

        return $barsAfterTouch >= 1
            && $barsAfterTouch <= max(1, (int) config('ict.max_entry_bars_after_retest', 3));
    }

    private function bullishTrigger(array $candles): bool
    {
        if (count($candles) < 2) return false;
        $last = $candles[array_key_last($candles)];
        $previous = $candles[count($candles) - 2];
        return (float) $last['close'] > (float) $last['open'] && (float) $last['close'] > (float) $previous['high'];
    }

    private function dealingRange(array $candles): array
    {
        $swings = $this->swings($candles);
        $latestHigh = $swings['highs'] === [] ? null : $swings['highs'][array_key_last($swings['highs'])];
        if ($latestHigh === null) {
            return ['high' => null, 'low' => null, 'equilibrium' => null, 'location' => 'UNCERTAIN', 'source' => 'NO_CONFIRMED_SWING_RANGE'];
        }

        // Anchor the bullish dealing range to the latest confirmed swing low
        // preceding the latest confirmed swing high. A rolling max/min window
        // can combine unrelated legs and mislabel premium/discount.
        $precedingLows = array_values(array_filter(
            $swings['lows'],
            fn (array $swing): bool => $swing['index'] < $latestHigh['index'],
        ));
        if ($precedingLows === []) {
            return ['high' => null, 'low' => null, 'equilibrium' => null, 'location' => 'UNCERTAIN', 'source' => 'NO_CONFIRMED_SWING_RANGE'];
        }

        $latestLow = $precedingLows[array_key_last($precedingLows)];
        $high = (float) $latestHigh['price'];
        $low = (float) $latestLow['price'];
        if ($high <= $low) {
            return ['high' => null, 'low' => null, 'equilibrium' => null, 'location' => 'UNCERTAIN', 'source' => 'INVALID_SWING_RANGE'];
        }

        $mid = ($high + $low) / 2;

        return [
            'high' => $high,
            'low' => $low,
            'equilibrium' => $mid,
            'location' => (float) $candles[array_key_last($candles)]['close'] < $mid ? 'DISCOUNT' : 'PREMIUM',
            'source' => 'LATEST_CONFIRMED_SWING_LOW_TO_HIGH',
            'swing_indices' => ['low' => $latestLow['index'], 'high' => $latestHigh['index']],
        ];
    }

    private function validSeries(array $candles): bool
    {
        if (count($candles) < max(20, (int) config('ict.minimum_candles', 30))) return false;
        $previous = -1;
        foreach ($candles as $c) {
            foreach (['open', 'high', 'low', 'close', 'volume', 'close_time'] as $key) if (! isset($c[$key]) || ! is_numeric($c[$key])) return false;
            if ((float) $c['high'] < max((float) $c['open'], (float) $c['close']) || (float) $c['low'] > min((float) $c['open'], (float) $c['close']) || (int) $c['close_time'] <= $previous || (int) $c['close_time'] > now()->getTimestampMs()) return false;
            $previous = (int) $c['close_time'];
        }
        return true;
    }

    private function unavailable(string $profile, string $reason): array
    {
        return ['engine' => 'ICT_COMPREHENSIVE', 'version' => config('ict.version', 'ict-v1'), 'profile' => $profile, 'direction' => 'LONG', 'scenarios' => [], 'decision' => 'REJECTED', 'reasons' => [$reason]];
    }
}
