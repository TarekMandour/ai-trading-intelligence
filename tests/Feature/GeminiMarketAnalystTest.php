<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiMarketAnalyst;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiMarketAnalystTest extends TestCase
{
    public function test_it_sends_an_independent_market_packet_and_parses_the_report(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'decision' => 'WATCHLIST', 'direction' => 'BULLISH', 'setup_type' => 'breakout_retest',
                    'entry_zone' => ['low' => 10.0, 'high' => 10.2], 'stop_loss' => 9.7,
                    'target_1' => 10.8, 'target_2' => 11.3, 'risk_reward' => 2.0,
                    'confidence' => 'MODERATE',
                    'timeframe_bias' => ['context' => 'BULLISH', 'structure' => 'BULLISH', 'setup' => 'BULLISH', 'entry' => 'WAIT'],
                    'key_evidence' => ['Higher highs'], 'risks' => ['Resistance nearby'],
                    'invalidation' => 'Close below 9.7', 'summary' => 'Wait for retest',
                ])]]]],
                'usageMetadata' => ['totalTokenCount' => 123],
            ]),
        ]);

        $packet = ['symbol' => 'TEST', 'market' => 'EGX', 'timeframes' => ['1d' => [['open' => 10, 'high' => 11, 'low' => 9, 'close' => 10.5, 'volume' => 1000]]]];
        $result = app(GeminiMarketAnalyst::class)->analyze($packet);

        $this->assertSame('COMPLETED', $result['status']);
        $this->assertSame('WATCHLIST', $result['report']['decision']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-test:generateContent')
            && str_contains($request->data()['contents'][0]['parts'][0]['text'], '"symbol":"TEST"'));
    }

    public function test_missing_gemini_key_fails_clearly(): void
    {
        config(['services.gemini.key' => null]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY is not configured.');
        app(GeminiMarketAnalyst::class)->analyze(['symbol' => 'TEST']);
    }
}
