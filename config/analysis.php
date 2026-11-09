<?php

return [
    'configuration_version' => 'phase-1-baseline-1',
    'universe_limit' => (int) env('ANALYSIS_UNIVERSE_LIMIT', 20),
    'binance_base_url' => env('BINANCE_BASE_URL', 'https://api.binance.com'),
    'timeout_seconds' => (int) env('BINANCE_TIMEOUT_SECONDS', 10),
    'order_book_limit' => (int) env('BINANCE_ORDER_BOOK_LIMIT', 20),
    'trade_notional' => (float) env('ANALYSIS_TRADE_NOTIONAL', 100),
    'fee_rate' => (float) env('ANALYSIS_FEE_RATE', 0.001),
    'profiles' => [
        'Scalping' => ['context' => '1h', 'structure' => '15m', 'setup' => '5m', 'entry' => '1m'],
        'Intraday' => ['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'],
        'Swing' => ['context' => '1w', 'structure' => '1d', 'setup' => '4h', 'entry' => '1h'],
    ],
];
