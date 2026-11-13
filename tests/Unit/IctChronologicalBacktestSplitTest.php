<?php

namespace Tests\Unit;

use App\Services\Analysis\IctComprehensiveEngine;
use App\Services\Analysis\IctHistoricalBacktestService;
use Tests\TestCase;

class IctChronologicalBacktestSplitTest extends TestCase
{
    private function series(int $count): array
    {
        $rows = [];
        $now = now()->getTimestampMs();
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'open_time' => $now - (($count - $i) * 60000),
                'close_time' => $now - (($count - $i) * 60000) + 59999,
                'open' => 100.0, 'high' => 101.0, 'low' => 99.0,
                'close' => 100.5, 'volume' => 100.0,
            ];
        }
        return $rows;
    }

    public function test_backtest_reports_separate_chronological_train_validation_and_test_windows(): void
    {
        $series = $this->series(110);
        $roles = ['context' => $series, 'structure' => $series, 'setup' => $series, 'entry' => $series];
        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldReceive('evaluate')->atLeast()->once()->andReturn(['scenarios' => []]);

        $result = (new IctHistoricalBacktestService($engine))->runChronologicalSplits($roles, 'Intraday', [
            'fee_rate' => 0, 'slippage_bps' => 0, 'minimum_history' => 30,
        ]);

        $this->assertSame('ICT_CHRONOLOGICAL_60_20_20_REPLAY', $result['engine']);
        $this->assertSame(['train', 'validation', 'test'], array_keys($result['splits']));
        $this->assertSame(['start' => 29, 'end' => 65], $result['split_indices']['train']);
        $this->assertSame(['start' => 66, 'end' => 87], $result['split_indices']['validation']);
        $this->assertSame(['start' => 88, 'end' => 109], $result['split_indices']['test']);
        $this->assertSame('NO_SCENARIO_TRADES', $result['splits']['test']['metrics_scope']);
        $this->assertSame('NOT_APPROVED_WITHOUT_PREDECLARED_THRESHOLDS_AND_PAPER_TRADING', $result['promotion_status']);
    }

    public function test_backtest_refuses_to_create_splits_without_enough_history(): void
    {
        $series = $this->series(40);
        $roles = ['context' => $series, 'structure' => $series, 'setup' => $series, 'entry' => $series];
        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldNotReceive('evaluate');

        $result = (new IctHistoricalBacktestService($engine))->runChronologicalSplits($roles, 'Intraday');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('INSUFFICIENT_DATA_FOR_CHRONOLOGICAL_60_20_20_SPLITS', $result['reason']);
    }
}
