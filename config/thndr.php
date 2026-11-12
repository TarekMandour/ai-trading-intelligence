<?php

return [
    'base_url' => env('THNDR_BASE_URL', 'https://prod.thndr.app'),
    'market' => env('THNDR_MARKET', 'egypt'),
    'capture_key' => env('THNDR_CAPTURE_KEY'),
    'user_agent' => env('THNDR_USER_AGENT', 'StockSignalEG/2.0'),
    'timeout_seconds' => (int) env('THNDR_TIMEOUT_SECONDS', 20),
    'resolutions' => [
        '1m' => '1MIN', '5m' => '5MIN', '10m' => '10MIN',
        '1h' => '1HR', '1d' => '1D', '1w' => '1W', '1M' => '1M',
    ],
    'candle_history_days' => (int) env('THNDR_CANDLE_HISTORY_DAYS', 365),
    'refresh_recent_candles' => (int) env('THNDR_REFRESH_RECENT_CANDLES', 2),
];
