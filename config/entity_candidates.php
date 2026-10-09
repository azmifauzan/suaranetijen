<?php

return [

    // A zero-result search query must repeat at least this many times before
    // it becomes a candidate — filters out one-off typos and noise.
    'min_search_query_frequency' => (int) env('ENTITY_CANDIDATES_MIN_SEARCH_FREQUENCY', 3),

    // Product candidates from these feeds, in these categories, whose name
    // starts with an existing brand, become entities without admin review.
    'auto_approve' => [
        'source_types' => ['wikidata'],
        'category_slugs' => ['smartphone', 'mobil', 'motor'],
    ],

];
