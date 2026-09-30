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
];
