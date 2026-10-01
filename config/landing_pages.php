<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Indexability Thresholds
    |--------------------------------------------------------------------------
    |
    | A topic landing page is indexable (and included in the sitemap) only if
    | at least `index_min_entities` have at least `index_min_mentions_per_entity`.
    |
    */
    'index_min_entities' => (int) env('LANDING_PAGES_INDEX_MIN_ENTITIES', 3),
    'index_min_mentions_per_entity' => (int) env('LANDING_PAGES_INDEX_MIN_MENTIONS', 3),

    /*
    |--------------------------------------------------------------------------
    | Candidate Scanner Thresholds
    |--------------------------------------------------------------------------
    */
    'search_query_min_signals' => (int) env('LANDING_PAGES_SEARCH_MIN_SIGNALS', 5),
    'search_query_window_days' => (int) env('LANDING_PAGES_SEARCH_WINDOW_DAYS', 30),
    'category_theme_min_entities' => (int) env('LANDING_PAGES_CAT_THEME_MIN_ENTITIES', 5),
    'category_theme_min_observations' => (int) env('LANDING_PAGES_CAT_THEME_MIN_OBSERVATIONS', 3),
    'scan_max_llm_calls' => (int) env('LANDING_PAGES_SCAN_MAX_LLM_CALLS', 20),

    /*
    |--------------------------------------------------------------------------
    | Complaint Subject Terms
    |--------------------------------------------------------------------------
    |
    | A category x theme candidate whose label contains one of these words is
    | never proposed, even when its sentiment is positive ("penanganan keluhan
    | baik"): the page would still list brands under a complaint headline.
    | Admins can create such a topic manually.
    |
    */
    'complaint_terms' => [
        'keluhan',
        'komplain',
        'complain',
        'lambat',
        'lemot',
        'bermasalah',
        'masalah',
        'kecewa',
        'penipuan',
        'tipu',
        'scam',
        'refund',
        'pengembalian dana',
        'rusak',
        'buruk',
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Blocklist
    |--------------------------------------------------------------------------
    */
    'blocklist' => [
        'judi',
        'slot',
        'togel',
        'gacor',
        'casino',
        'porn',
        'bokep',
        'judol',
        'zeus',
        'maxwin',
        'pragmatic',
        'sbobet',
        'taruhan',
        'sex',
        'lendir',
        'openbo',
    ],
];
