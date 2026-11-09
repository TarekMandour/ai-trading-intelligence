<?php

namespace Tests\Unit;

use App\Services\Analysis\DeterministicAnalysisEngine;
use PHPUnit\Framework\TestCase;

class DeterministicAnalysisEngineTest extends TestCase
{
    public function test_rejects_forming_candles_without_creating_a_candidate(): void
    {
        $candles = array_fill(0, 20, ['open_time' => 1, 'close_time' => 9_999_999_999_999, 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1.0]);
        $result = (new DeterministicAnalysisEngine)->evaluate($candles);
        $this->assertSame('UNCERTAIN', $result['direction']);
        $this->assertSame(null, $result['candidate']);
    }
}
