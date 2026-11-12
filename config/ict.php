<?php

return [
    'enabled' => (bool) env('ICT_STRATEGY_ENABLED', true),
    'version' => env('ICT_STRATEGY_VERSION', 'ict-v1'),
    'minimum_candles' => (int) env('ICT_MINIMUM_CANDLES', 30),
    'swing_left' => (int) env('ICT_SWING_LEFT', 2),
    'swing_right' => (int) env('ICT_SWING_RIGHT', 2),
    'displacement_atr_multiple' => (float) env('ICT_DISPLACEMENT_ATR_MULTIPLE', 1.2),
    'displacement_body_ratio' => (float) env('ICT_DISPLACEMENT_BODY_RATIO', 0.65),
    'dealing_range_lookback' => (int) env('ICT_DEALING_RANGE_LOOKBACK', 50),
    'target_1_r' => (float) env('ICT_TARGET_1_R', 2.0),
    'target_2_r' => (float) env('ICT_TARGET_2_R', 3.0),
    'kill_zones' => '24h',
    'direction' => 'LONG',
    'entry_methods' => ['MARKET', 'LIMIT'],
];
