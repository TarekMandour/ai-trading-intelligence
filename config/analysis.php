<?php

return [
    'configuration_version' => 'phase-1-baseline-1',
    'universe_limit' => (int) env('ANALYSIS_UNIVERSE_LIMIT', 20),
    // Exclude stablecoins when they are the base asset (for example, USDCUSDT).
    'excluded_base_assets' => array_values(array_filter(array_map('strtoupper', array_map('trim', explode(',', env('ANALYSIS_EXCLUDED_BASE_ASSETS', 'USDT,USDC,BUSD,TUSD,FDUSD,DAI,USDP,USD1,USDE,PYUSD,UST'))))),
    'binance_base_url' => env('BINANCE_BASE_URL', 'https://api.binance.com'),
    'timeout_seconds' => (int) env('BINANCE_TIMEOUT_SECONDS', 10),
    'exchange_info_timeout_seconds' => (int) env('BINANCE_EXCHANGE_INFO_TIMEOUT_SECONDS', 60),
    'exchange_info_cache_minutes' => (int) env('BINANCE_EXCHANGE_INFO_CACHE_MINUTES', 60),
    'order_book_limit' => (int) env('BINANCE_ORDER_BOOK_LIMIT', 20),
    'trade_notional' => (float) env('ANALYSIS_TRADE_NOTIONAL', 100),
    'fee_rate' => (float) env('ANALYSIS_FEE_RATE', 0.001),
    'minimum_net_risk_reward' => (float) env('ANALYSIS_MIN_NET_RISK_REWARD', 1.5),
    'ai_max_candidates_per_run' => (int) env('AI_MAX_CANDIDATES_PER_RUN', 5),
    'profiles' => [
        'Scalping' => ['context' => '1h', 'structure' => '15m', 'setup' => '5m', 'entry' => '1m'],
        'Intraday' => ['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'],
        'Swing' => ['context' => '1w', 'structure' => '1d', 'setup' => '4h', 'entry' => '1h'],
    ],
];
