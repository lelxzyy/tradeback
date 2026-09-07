<?php
return [
    'symbol' => env('MARKET_SYMBOL', 'XAU/USD'),
    'cache_store' => env('TRADING_CACHE_STORE', 'file'),
    'quote_ttl' => (int) env('MARKET_QUOTE_TTL', 120),
    'candles_ttl' => (int) env('MARKET_CANDLES_TTL', 600),
    'quiet_timezone' => env('MARKET_QUIET_TIMEZONE', 'Asia/Jakarta'),
    'quiet_start_hour' => (int) env('MARKET_QUIET_START_HOUR', 2),
    'quiet_end_hour' => (int) env('MARKET_QUIET_END_HOUR', 7),
];
