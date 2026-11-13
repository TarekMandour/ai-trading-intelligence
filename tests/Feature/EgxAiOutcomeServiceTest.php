<?php

namespace Tests\\Feature;

use App\\Models\\EgxScanRun;
use App\\Models\\EgxWatchlist;
use App\\Services\\MarketData\\EgxAiOutcomeService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use Tests\\TestCase;

class EgxAiOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_use_candles_at_or_before_the_ai_timestamp(): void
    {
        $watchlist = EgxWatchlist::create(['name' => 'Test']);
        EgxScanRun::create([
            'egx_watchlist_id' => $watchlist->id,
            'status' => 'COMPLETED',
            'profiles' => ['Scalping'],
            'results' => [[
                'asset_id' => 'asset-1',
                'symbol' => 'TEST',
                'profiles' => ['Scalping' => [
                    'gemini_analysis' => [
                        'status' => 'COMPLETED',
                        'as_of' => '2026-11-10T10:00:00+02:00',
                        'report' => [
                            'decision' => 'ENTER_NOW',
                            'entry_zone' => ['low' => 10, 'high' => 10.1],
                            'stop_loss' => 9.5,
                            'target_1' => 11,
                        ],
                    ],
                ]],
            ]],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        DB::table('egx_candles')->insert([
            [
                'asset_id' => 'asset-1',
                'timeframe' => '1m',
                'open_time' => strtotime('2026-11-10T09:59:00+02:00') * 1000,
                'close_time' => strtotime('2026-11-10T09:59:59+02:00') * 1000,
                'open' => 10,
                'high' => 11.2,
                'low' => 9.8,
                'close' => 10.5,
                'volume' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = app(EgxAiOutcomeService::class)->evaluate('asset-1', 1);

        $this->assertSame(0, $result['evaluated_reports']);
    }
}
