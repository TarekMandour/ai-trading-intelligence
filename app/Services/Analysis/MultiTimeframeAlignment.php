<?php

namespace App\Services\Analysis;

class MultiTimeframeAlignment
{
    /** @param array<string, array{direction:string,behavior:string}> $states */
    public function summarize(array $states): array
    {
        if (count($states) < 4 || collect($states)->contains(fn (array $state): bool => $state['direction'] === 'UNCERTAIN')) {
            return ['state' => 'INSUFFICIENT', 'reasons' => ['TIMEFRAME_EVIDENCE_INSUFFICIENT']];
        }
        $directions = array_column($states, 'direction');
        if (count(array_unique($directions)) === 1) {
            return ['state' => 'ALIGNED', 'reasons' => ['DIRECTIONAL_ALIGNMENT']];
        }
        if (in_array('BULLISH', $directions, true) && in_array('BEARISH', $directions, true)) {
            return ['state' => 'CONFLICTED', 'reasons' => ['HIGHER_LOWER_TIMEFRAME_CONFLICT']];
        }

        return ['state' => 'PARTIAL', 'reasons' => ['PARTIAL_DIRECTIONAL_ALIGNMENT']];
    }
}
