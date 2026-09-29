<?php

// Entity alias hygiene for EntityMatcher and source discovery (docs/10, precision over recall).

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked Aliases
    |--------------------------------------------------------------------------
    |
    | Normalized aliases that are everyday words, slang, or ambiguous between
    | entities. They are never matched against opinion text, never used as
    | source search queries, and cannot be added through the admin or seed
    | import. "ga" is Indonesian slang for "tidak"; matching it as Garuda
    | Indonesia attributed lotion and car opinions to the airline.
    |
    */
    'blocked_aliases' => ['ga', 'do', 'map', 'sap', 'tam', 'bl', 'ct', 'rk', 'garuda'],

    /*
    |--------------------------------------------------------------------------
    | Uppercase-Only Alias Length
    |--------------------------------------------------------------------------
    |
    | Letter-only aliases up to this many characters (BCA, KAI, XL) only match
    | when written in capitals in the original text, since the lowercase form
    | is usually an ordinary word.
    |
    */
    'uppercase_only_max_length' => 3,

];
