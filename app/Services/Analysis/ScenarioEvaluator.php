<?php

namespace App\Services\Analysis;

class ScenarioEvaluator
{
    /** @return array<int, array{scenario:string,status:string,reasons:array<int,string>}> */
    public function evaluate(array $regime): array
    {
        $bullTrend = $regime['direction'] === 'BULLISH' && $regime['behavior'] === 'TRENDING';

        return [
            ['scenario' => 'TREND_PULLBACK_CONTINUATION', 'status' => $bullTrend ? 'WAIT' : 'REJECTED', 'reasons' => [$bullTrend ? 'PULLBACK_CONFIRMATION_REQUIRED' : 'TREND_CONTEXT_MISSING']],
            ['scenario' => 'BREAKOUT_RETEST', 'status' => 'WAIT', 'reasons' => ['BREAKOUT_RETEST_CONFIRMATION_REQUIRED']],
            ['scenario' => 'RANGE_MEAN_REVERSION', 'status' => $regime['direction'] === 'RANGING' ? 'WAIT' : 'REJECTED', 'reasons' => ['RANGE_BOUNDARY_CONFIRMATION_REQUIRED']],
            ['scenario' => 'MOMENTUM_VOLATILITY_EXPANSION', 'status' => $regime['volatility'] === 'HIGH' ? 'WAIT' : 'REJECTED', 'reasons' => ['EXPANSION_CONFIRMATION_REQUIRED']],
        ];
    }
}
