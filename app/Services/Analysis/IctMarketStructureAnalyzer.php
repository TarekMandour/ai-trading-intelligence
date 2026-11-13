<?php

namespace App\\Services\\Analysis;

/** Builds chronological BOS/CHoCH events from already-confirmed pivots. */
class IctMarketStructureAnalyzer
{
    public function __construct(private readonly IctSwingPointDetector $pivots = new IctSwingPointDetector) {}

    public function analyze(array $candles): array
    {
        $swings = $this->pivots->detect($candles);
        $events = [];
        $bias = 'UNCERTAIN';
        $brokenHighs = [];
        $brokenLows = [];
        $highCursor = $lowCursor = 0;

        for ($i = 0, $count = count($candles); $i < $count; $i++) {
            while (isset($swings['highs'][$highCursor]) && $swings['highs'][$highCursor]['confirmation_index'] < $i) $highCursor++;
            while (isset($swings['lows'][$lowCursor]) && $swings['lows'][$lowCursor]['confirmation_index'] < $i) $lowCursor++;
            $close = (float) $candles[$i]['close'];

            for ($p = 0; $p < $highCursor; $p++) {
                $pivot = $swings['highs'][$p];
                if (isset($brokenHighs[$pivot['index']]) || $close <= $pivot['price']) continue;
                $type = $bias === 'BEARISH' ? 'CHOCH_BULLISH' : 'BOS_BULLISH';
                $events[] = $this->event($type, $pivot, $i, $candles);
                $bias = 'BULLISH';
                $brokenHighs[$pivot['index']] = true;
            }

            for ($p = 0; $p < $lowCursor; $p++) {
                $pivot = $swings['lows'][$p];
                if (isset($brokenLows[$pivot['index']]) || $close >= $pivot['price']) continue;
                $type = $bias === 'BULLISH' ? 'CHOCH_BEARISH' : 'BOS_BEARISH';
                $events[] = $this->event($type, $pivot, $i, $candles);
                $bias = 'BEARISH';
                $brokenLows[$pivot['index']] = true;
            }
        }

        $last = $events === [] ? null : $events[array_key_last($events)];
        $recentHighs = array_slice($swings['highs'], -2);
        $recentLows = array_slice($swings['lows'], -2);
        $swingBias = 'UNCERTAIN';
        if (count($recentHighs) === 2 && count($recentLows) === 2) {
            if ($recentHighs[1]['price'] > $recentHighs[0]['price'] && $recentLows[1]['price'] > $recentLows[0]['price']) $swingBias = 'BULLISH';
            elseif ($recentHighs[1]['price'] < $recentHighs[0]['price'] && $recentLows[1]['price'] < $recentLows[0]['price']) $swingBias = 'BEARISH';
            else $swingBias = 'RANGING';
        }

        return [
            'bias' => $bias !== 'UNCERTAIN' ? $bias : $swingBias,
            'swing_bias' => $swingBias,
            'events' => $events,
            'last_event' => $last,
            'swings' => $swings,
            'broken_levels' => ['highs' => array_keys($brokenHighs), 'lows' => array_keys($brokenLows)],
            'causal' => true,
        ];
    }

    private function event(string $type, array $pivot, int $index, array $candles): array
    {
        return [
            'type' => $type,
            'direction' => str_contains($type, 'BULLISH') ? 'BULLISH' : 'BEARISH',
            'level' => $pivot['price'],
            'pivot_index' => $pivot['index'],
            'pivot_confirmed_at_index' => $pivot['confirmation_index'],
            'break_index' => $index,
            'break_close' => (float) $candles[$index]['close'],
            'close_time' => (int) ($candles[$index]['close_time'] ?? 0),
            'confirmation' => 'CANDLE_CLOSE_BEYOND_CONFIRMED_PIVOT',
        ];
    }
}
