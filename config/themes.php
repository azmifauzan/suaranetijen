<?php

// Configuration for Top Suara Netijen (Theme Index) per docs/25.
// Minimum thresholds are configuration, calibrated as data grows.

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Qualified Opinions for Entity
    |--------------------------------------------------------------------------
    |
    | An entity must have at least this number of qualified opinions before
    | Top Suara Netijen will be displayed (docs/25 line 145).
    |
    */
    'min_entity_opinions' => (int) env('THEMES_MIN_ENTITY_OPINIONS', 30),

    /*
    |--------------------------------------------------------------------------
    | Minimum Occurrences per Displayed Theme
    |--------------------------------------------------------------------------
    |
    | A theme must appear at least this many times for an entity to be
    | eligible for display (docs/25 line 147).
    |
    */
    'min_theme_occurrences' => (int) env('THEMES_MIN_THEME_OCCURRENCES', 3),

    /*
    |--------------------------------------------------------------------------
    | Default Top Themes Limit
    |--------------------------------------------------------------------------
    |
    | Default number of themes to show in Top Suara Netijen (MVP Top 5).
    |
    */
    'default_limit' => (int) env('THEMES_DEFAULT_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Empty State Copy
    |--------------------------------------------------------------------------
    |
    | Displayed when the entity or themes fall below configured thresholds.
    |
    */
    'empty_state_message' => 'Belum cukup opini untuk merangkum Suara Netijen.',

];
