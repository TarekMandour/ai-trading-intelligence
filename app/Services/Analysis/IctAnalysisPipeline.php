<?php

namespace App\\Services\\Analysis;

/** Coordinates the causal ICT evidence pipeline for one completed-candle series. */
class IctAnalysisPipeline
{
    public function __construct(
        private readonly IctSwingPointDetector $pivots = new IctSwingPointDetector,
        private readonly IctMarketStructureAnalyzer $structureAnalyzer = new IctMarketStructureAnalyzer,
        private readonly IctLiquidityAnalyzer $liquidityAnalyzer = new IctLiquidityAnalyzer,
        private readonly IctDisplacementAnalyzer $displacementAnalyzer = new IctDisplacementAnalyzer,
        private readonly IctZoneQualityAnalyzer $zoneAnalyzer = new IctZoneQualityAnalyzer,
        private readonly IctDealingRangeAnalyzer $rangeAnalyzer = new IctDealingRangeAnalyzer,
        private readonly IctStructuralTradePlanner $tradePlanner = new IctStructuralTradePlanner,
    ) {}

    public function analyze(array $candles, array $entryCandles = []): array
    {
        $structure = $this->structureAnalyzer->analyze($candles);
        $liquidity = $this->liquidityAnalyzer->analyze($candles, $structure['swings']);
        $displacement = $this->displacementAnalyzer->analyze($candles);
        $zones = $this->zoneAnalyzer->analyze($candles, $displacement, $structure, $liquidity);
        $range = $this->rangeAnalyzer->analyze($candles, $structure);
        $tradePlan = $this->tradePlanner->plan($candles, $structure, $liquidity, $displacement, $zones, $range, $entryCandles);

        return [
            'market_structure' => $structure,
            'liquidity' => $liquidity,
            'displacement' => $displacement,
            'zones' => $zones,
            'dealing_range' => $range,
            'trade_plan' => $tradePlan,
            'version' => 'ict-causal-pipeline-v2',
        ];
    }
}
