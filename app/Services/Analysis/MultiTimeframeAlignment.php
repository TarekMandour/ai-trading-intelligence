<?php

namespace App\\Services\\Analysis;

class MultiTimeframeAlignment
{
    /**
     * Context sets the permitted direction, Structure protects the higher-timeframe
     * trend, Setup may retrace, and Entry must confirm a bullish resumption.
     *
     * @param array<string, array{direction:string,behavior:string}> $states
     */
    public function summarize(array $states): array
    {
        foreach (['context', 'structure', 'setup', 'entry'] as $role) {
            if (! isset($states[$role]['direction']) || $states[$role]['direction'] === 'UNCERTAIN') {
                return [
                    'state' => 'INSUFFICIENT',
                    'allow_entry' => false,
                    'reasons' => ['TIMEFRAME_EVIDENCE_INSUFFICIENT', 'MISSING_OR_UNCERTAIN_'.strtoupper($role)],
                ];
            }
        }

        $context = $states['context']['direction'];
        $structure = $states['structure']['direction'];
        $setup = $states['setup']['direction'];
        $entry = $states['entry']['direction'];

        if ($context !== 'BULLISH') {
            return [
                'state' => 'CONFLICTED',
                'allow_entry' => false,
                'reasons' => [$context === 'BEARISH' ? 'BEARISH_CONTEXT_BLOCKS_LONG' : 'NON_BULLISH_CONTEXT_BLOCKS_LONG'],
            ];
        }

        if ($structure === 'BEARISH') {
            return ['state' => 'INVALIDATED', 'allow_entry' => false, 'reasons' => ['HIGHER_TIMEFRAME_STRUCTURE_BEARISH']];
        }

        if (! in_array($structure, ['BULLISH', 'RANGING'], true)) {
            return ['state' => 'INSUFFICIENT', 'allow_entry' => false, 'reasons' => ['UNSUPPORTED_STRUCTURE_STATE']];
        }

        if ($entry !== 'BULLISH') {
            return [
                'state' => 'PARTIAL',
                'allow_entry' => false,
                'reasons' => [$entry === 'BEARISH' ? 'ENTRY_TIMEFRAME_NOT_BULLISH' : 'ENTRY_CONFIRMATION_MISSING'],
            ];
        }

        if ($setup === 'BEARISH') {
            return ['state' => 'PULLBACK', 'allow_entry' => true, 'reasons' => ['BULLISH_CONTEXT_CONFIRMED', 'HEALTHY_PULLBACK', 'LOWER_TIMEFRAME_RECLAIM_CONFIRMED']];
        }

        if ($setup === 'RANGING' || $structure === 'RANGING') {
            return ['state' => 'PARTIAL', 'allow_entry' => true, 'reasons' => ['BULLISH_CONTEXT_CONFIRMED', 'STRUCTURE_OR_SETUP_CONSOLIDATING', 'ENTRY_CONFIRMATION_CONFIRMED']];
        }

        if ($setup === 'BULLISH') {
            return ['state' => 'ALIGNED', 'allow_entry' => true, 'reasons' => ['BULLISH_CONTEXT_CONFIRMED', 'BULLISH_STRUCTURE_CONFIRMED', 'BULLISH_SETUP_AND_ENTRY_CONFIRMED']];
        }

        return ['state' => 'INSUFFICIENT', 'allow_entry' => false, 'reasons' => ['UNSUPPORTED_SETUP_STATE']];
    }
}
