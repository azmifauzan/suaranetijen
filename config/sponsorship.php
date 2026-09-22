<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sponsorship Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Papan Sponsor paid leaderboard (docs/26).
    | Ranks active entities by verified sponsor contributions.
    |
    */

    'min_amount' => (int) env('SPONSORSHIP_MIN_AMOUNT', 1000),

    'increment_amount' => (int) env('SPONSORSHIP_INCREMENT_AMOUNT', 1),

    'currency' => 'IDR',

    'sumopod' => [
        'base_url' => rtrim((string) env('SUMOPOD_BASE_URL', 'https://api-pay.sumopod.com'), '/'),
        'api_key' => (string) env('SUMOPOD_API_KEY', ''),
        'relay_secret' => (string) env('SUMOPOD_RELAY_SECRET', ''),
        'relay_tolerance' => (int) env('SUMOPOD_RELAY_TOLERANCE', 300),
        'order_prefix' => (string) env('SUMOPOD_ORDER_PREFIX', 'SNT-SPN-'),
    ],

    'telegram' => [
        'bot_token' => (string) env('SPONSORSHIP_TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => (string) env('SPONSORSHIP_TELEGRAM_CHAT_ID', ''),
    ],

];
