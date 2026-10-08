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
    'blocked_aliases' => ['ga', 'do', 'map', 'sap', 'tam', 'bl', 'ct', 'rk', 'garuda', 'gigi'],

    /*
    |--------------------------------------------------------------------------
    | Context-Required Aliases
    |--------------------------------------------------------------------------
    |
    | Brand names that are also everyday words. The term only matches when the
    | text also contains one of its context words, so "jago" (Indonesian for
    | "skilled") in a car review is not attributed to the bank.
    |
    */
    'context_required_aliases' => [
        'jago' => ['bank', 'rekening', 'kantong', 'saldo', 'tabungan', 'transfer', 'aplikasi', 'app', 'nasabah', 'debit', 'kartu', 'atm', 'bunga', 'deposito', 'ojk', 'lps', 'syariah', 'mbanking', 'qris'],
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Model Variant Suffixes
    |--------------------------------------------------------------------------
    |
    | A model-number alias ("s24", "iphone 15") does not match when the text
    | continues with one of these tokens ("S24 FE", "iPhone 15 Pro"): that is a
    | different model, and attributing it to the base model skews its score. An
    | entity whose own name or alias is the longer phrase still wins by length.
    |
    */
    'model_variant_suffixes' => ['fe', 'plus', 'ultra', 'pro', 'max', 'lite', 'mini', 'se', 'edge'],

    /*
    |--------------------------------------------------------------------------
    | Candidate Cache
    |--------------------------------------------------------------------------
    |
    | Seconds the normalized name/alias set of all active entities is cached for
    | EntityMatcher. 0 disables the cache (tests).
    |
    */
    'candidates_cache_seconds' => (int) env('ENTITY_MATCHER_CACHE_SECONDS', 60),

];
