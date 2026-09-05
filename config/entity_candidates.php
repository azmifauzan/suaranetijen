<?php

return [

    // A zero-result search query must repeat at least this many times before
    // it becomes a candidate — filters out one-off typos and noise.
    'min_search_query_frequency' => (int) env('ENTITY_CANDIDATES_MIN_SEARCH_FREQUENCY', 3),

];
