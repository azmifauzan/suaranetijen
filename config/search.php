<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search Stopwords
    |--------------------------------------------------------------------------
    | Stopwords removed during query tokenization (docs/30).
    | Subjective superlatives ("terbaik", "bagus", etc.) are handled by Sentimen
    | Netijen scoring, not textual matching. Concrete adjectives ("murah", "awet",
    | "cepat") are deliberately preserved to match theme labels.
    */
    'stopwords' => [
        'yang',
        'dan',
        'di',
        'untuk',
        'dengan',
        'paling',
        'terbaik',
        'bagus',
        'rekomendasi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Query Tokens
    |--------------------------------------------------------------------------
    | Cap to prevent query explosion from long automated bot inputs.
    */
    'max_query_tokens' => 8,

    /*
    |--------------------------------------------------------------------------
    | Homepage Search Suggestions
    |--------------------------------------------------------------------------
    | Chips under the search box: keywords searched by at least `min_sessions`
    | different visitors in the last `window_days`, cached `cache_seconds`.
    */
    'suggestions' => [
        'limit' => 6,
        'window_days' => 30,
        'min_sessions' => 3,
        'cache_seconds' => 3600,
    ],
];
