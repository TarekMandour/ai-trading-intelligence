<?php

namespace Tests\Unit;

use App\Services\Analysis\IctComprehensiveEngine;
use App\Services\Analysis\IctHistoricalBacktestService;
use Tests\TestCase;

class IctReplayIntegrityTest extends TestCase
{
    private function candles(int $count = 50): array
    {
        $candles = [];
        for ($i = 0; $i < $count; $i++) {
            $open = 100.0;
            $close = 100.0;
            $candles[] = [
                'open_time' => $i * 60000,
                'close_time' => ($i + 1) * 60000 - 1,
                'open' => $open,
                'high' => 101.0,
                'low' => 99.0,
                'close' => $close,
                'volume' => 1000.0,
            ];
        }
        return $candles;
    }

    public function test_backtest_reanchors_stop_and_targets_to_actual_market_fill(): void
    {
        $candles = $this->candles();
        $candles[30]['close'] = 100.5;
        $candles[30]['high'] = 101.0;
        $candles[31]['open'] = 105.0;
        $candles[31]['high'] = 106.0;
        $candles[31]['low'] = 101.0;
        $candles[31]['close'] = 104.5;
        // Later bars stay valid OHLC; the stop must not be touched during entry bar.
        $candles[32]['open'] = 104.0;
        $candles[32]['high'] = 105.0;
        $candles[32]['low'] = 101.0;
        $candles[32]['close'] = 102.0;

        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldReceive('evaluate')->atLeast()->once()->andReturn([
            'scenarios' => [[
                'scenario' => 'ICT_TEST_MARKET',
                'status' => 'ENTERABLE',
                'candidate' => [
                    'entry' => 100.0,
                    'stop' => 95.0,
                    'target1' => 110.0,
                    'target2' => 115.0,
                    'entry_plans' => ['market' => ['status' => 'PLANNED'], 'limit' => ['status' => 'WAIT', 'limit_price' => 99.0]],
                ],
            ]],
        ]);

        $result = (new IctHistoricalBacktestService($engine))->run(
            ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles],
            'Scalping',
            ['fee_rate' => 0, 'slippage_bps' => 0, 'minimum_history' => 30, 'test_start_index' => 30, 'max_holding_bars' => 2],
        );

        $trade = $result['trades'][0];
        $this->assertSame(105.0, $trade['entry_price']);
        $this->assertSame(100.0, $trade['stop']);
        $this->assertSame(115.0, $trade['target1']);
        $this->assertSame(120.0, $trade['target2']);
        $this->assertSame(5.0, $trade['risk_per_unit']);
    }

    public function test_limit_gap_open_is_filled_at_better_open_price_not_limit_touch_price(): void
    {
        $candles = $this->candles();
        $candles[31]['open'] = 98.0;
        $candles[31]['high'] = 100.0;
        $candles[31]['low'] = 97.0;
        $candles[31]['close'] = 99.0;

        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldReceive('evaluate')->atLeast()->once()->andReturn([
            'scenarios' => [[
                'scenario' => 'ICT_TEST_LIMIT',
                'status' => 'WAIT',
                'candidate' => [
                    'entry' => 100.0, 'stop' => 95.0, 'target1' => 110.0, 'target2' => 115.0,
                    'entry_plans' => ['market' => ['status' => 'WAIT'], 'limit' => ['status' => 'PLANNED', 'limit_price' => 99.0]],
                ],
            ]],
        ]);

        $result = (new IctHistoricalBacktestService($engine))->run(
            ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles],
            'Scalping',
            ['fee_rate' => 0, 'slippage_bps' => 0, 'minimum_history' => 30, 'test_start_index' => 30, 'max_holding_bars' => 2],
        );

        $this->assertSame(98.0, $result['trades'][0]['entry_price']);
        $this->assertSame('LIMIT_GAP_OPEN_BETTER_PRICE_ASSUMED', $result['trades'][0]['fill_assumption']);
    }

    public function test_backtest_uses_independent_scenario_results_instead_of_array_order(): void
    {
        $candles = $this->candles();
        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldReceive('evaluate')->atLeast()->once()->andReturn([
            'scenarios' => [
                ['scenario' => 'ICT_FIRST_LIMIT', 'status' => 'WAIT', 'candidate' => [
                    'entry' => 100.0, 'stop' => 95.0, 'target1' => 110.0, 'target2' => 115.0,
                    'entry_plans' => ['market' => ['status' => 'WAIT'], 'limit' => ['status' => 'PLANNED', 'limit_price' => 99.0]],
                ]],
                ['scenario' => 'ICT_SECOND_MARKET', 'status' => 'ENTERABLE', 'candidate' => [
                    'entry' => 100.0, 'stop' => 95.0, 'target1' => 110.0, 'target2' => 115.0,
                    'entry_plans' => ['market' => ['status' => 'PLANNED'], 'limit' => ['status' => 'PLANNED', 'limit_price' => 99.0]],
                ]],
            ],
        ]);

        $result = (new IctHistoricalBacktestService($engine))->run(
            ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles],
            'Scalping',
            ['fee_rate' => 0, 'slippage_bps' => 0, 'minimum_history' => 30, 'test_start_index' => 30, 'max_holding_bars' => 2],
        );

        $this->assertNotEmpty($result['trades']);
        $this->assertContains('ICT_SECOND_MARKET', array_column($result['trades'], 'scenario'));
        $this->assertContains('ICT_FIRST_LIMIT', array_column($result['trades'], 'scenario'));
        $this->assertNull($result['metrics']);
        $this->assertSame('PER_SCENARIO_ONLY_NO_CROSS_STRATEGY_PORTFOLIO', $result['metrics_scope']);
        $this->assertArrayHasKey('ICT_FIRST_LIMIT', $result['scenario_metrics']);
        $this->assertArrayHasKey('ICT_SECOND_MARKET', $result['scenario_metrics']);
    }

    public function test_holdout_index_beyond_dataset_returns_structured_empty_result(): void
    {
        $candles = $this->candles();
        $result = (new IctHistoricalBacktestService(new IctComprehensiveEngine))->run(
            ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles],
            'Scalping',
            ['test_start_index' => 500],
        );

        $this->assertSame(0, $result['counts']['trades']);
        $this->assertContains('BACKTEST_HOLDOUT_HAS_NO_FORWARD_BARS', $result['reasons']);
    }
    public function test_scenario_families_keep_independent_equity_and_compound_size(): void
    {
        $candles = $this->candles();
        $candles[31]['high'] = 111.0;
        $candles[31]['close'] = 110.0;
        $candles[31]['low'] = 99.0;
        $candles[32]['high'] = 112.0;
        $candles[32]['close'] = 111.0;

        $engine = $this->mock(IctComprehensiveEngine::class);
        $engine->shouldReceive('evaluate')->atLeast()->once()->andReturn([
            'scenarios' => [[
                'scenario' => 'ICT_ISOLATED_FAMILY',
                'status' => 'ENTERABLE',
                'candidate' => [
                    'entry' => 100.0, 'stop' => 95.0, 'target1' => 110.0, 'target2' => 115.0,
                    'entry_plans' => ['market' => ['status' => 'PLANNED'], 'limit' => ['status' => 'WAIT']],
                ],
            ]],
        ]);

        $result = (new IctHistoricalBacktestService($engine))->run(
            ['context' => $candles, 'structure' => $candles, 'setup' => $candles, 'entry' => $candles],
            'Scalping',
            [
                'fee_rate' => 0, 'slippage_bps' => 0, 'minimum_history' => 30,
                'test_start_index' => 30, 'max_holding_bars' => 1,
                'initial_capital' => 10000, 'risk_fraction' => 0.01,
            ],
        );

        $scenarioTrades = array_values(array_filter(
            $result['trades'],
            fn (array $trade): bool => $trade['scenario'] === 'ICT_ISOLATED_FAMILY',
        ));
        $this->assertNotEmpty($scenarioTrades);
        $this->assertSame(100.0, $scenarioTrades[0]['planned_risk_cash']);
        $this->assertSame('SINGLE_SCENARIO', $result['metrics_scope']);
    }

}
