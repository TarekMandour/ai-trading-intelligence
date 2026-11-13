<?php

namespace Tests\\Unit;

use App\\Services\\Analysis\\IctOpportunityWatchlist;
use Tests\\TestCase;

class IctOpportunityWatchlistTest extends TestCase
{
    public function test_bullish_market_structure_near_active_fvg_creates_watchlist_not_entry(): void
    {
        $result = (new IctOpportunityWatchlist)->evaluate(
            ['bias' => 'RANGING', 'structure_bias' => 'BULLISH'],
            [
                'mss' => ['confirmed' => true],
                'sequence' => ['status' => 'NOT_CONFIRMED'],
                'fvg' => ['low' => 126.0, 'high' => 127.0, 'ce' => 126.5],
                'fvg_lifecycle' => ['status' => 'ACTIVE', 'touch_count' => 0],
            ],
            [],
            ['last_close' => 127.4, 'bullish_trigger' => false],
        );

        $this->assertNotNull($result);
        $this->assertSame('WATCHLIST', $result['status']);
        $this->assertSame('BULLISH_FVG', $result['zone_type']);
        $this->assertFalse($result['entry_authorized']);
        $this->assertContains('WAITING_FOR_BULLISH_ENTRY_TRIGGER', $result['reasons']);
        $this->assertSame(126.0, $result['zone']['low']);
    }

    public function test_watchlist_rejects_bearish_context_even_when_zone_is_nearby(): void
    {
        $result = (new IctOpportunityWatchlist)->evaluate(
            ['bias' => 'BEARISH', 'structure_bias' => 'BULLISH'],
            [
                'mss' => ['confirmed' => true],
                'fvg' => ['low' => 126.0, 'high' => 127.0],
                'fvg_lifecycle' => ['status' => 'ACTIVE'],
            ],
            [],
            ['last_close' => 127.0, 'bullish_trigger' => false],
        );

        $this->assertNull($result);
    }

    public function test_watchlist_rejects_price_far_from_all_active_zones(): void
    {
        $result = (new IctOpportunityWatchlist)->evaluate(
            ['bias' => 'RANGING', 'structure_bias' => 'BULLISH'],
            [
                'mss' => ['confirmed' => true],
                'fvg' => ['low' => 100.0, 'high' => 101.0],
                'fvg_lifecycle' => ['status' => 'ACTIVE'],
            ],
            [],
            ['last_close' => 110.0, 'bullish_trigger' => false],
        );

        $this->assertNull($result);
    }

    public function test_invalidated_fvg_does_not_create_watchlist(): void
    {
        $result = (new IctOpportunityWatchlist)->evaluate(
            ['bias' => 'RANGING', 'structure_bias' => 'BULLISH'],
            [
                'mss' => ['confirmed' => true],
                'fvg' => ['low' => 126.0, 'high' => 127.0],
                'fvg_lifecycle' => ['status' => 'INVALIDATED'],
            ],
            [],
            ['last_close' => 127.0, 'bullish_trigger' => false],
        );

        $this->assertNull($result);
    }
}
