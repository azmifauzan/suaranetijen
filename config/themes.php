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
    | Theme Extractor
    |--------------------------------------------------------------------------
    |
    | 'keyword' matches the seeded dictionary (ThemeExtractor). 'llm' extracts
    | specific theme phrases per opinion via LlmClient (LlmThemeExtractor).
    | Aggregation only counts observations produced by the active extractor.
    |
    */
    'extractor' => env('THEMES_EXTRACTOR', 'keyword'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Opinion Length for LLM Extraction
    |--------------------------------------------------------------------------
    |
    | Opinions shorter than this many characters are skipped by the LLM
    | extractor: they rarely hold a concrete judgement and cost a call each.
    |
    */
    'llm_min_chars' => (int) env('THEMES_LLM_MIN_CHARS', 30),

    /*
    |--------------------------------------------------------------------------
    | LLM Calls per Minute
    |--------------------------------------------------------------------------
    |
    | Cluster-wide cap (shared Redis limiter) on theme-extraction LLM calls.
    | Jobs over the cap are released back to the queue, never failed.
    |
    */
    'llm_per_minute' => (int) env('THEMES_LLM_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | LLM Batch Size
    |--------------------------------------------------------------------------
    |
    | Opinions sent to the LLM per call during batch backfill (themes:backfill-batch).
    | Batching amortizes one instruction+known-label prompt over many opinions and
    | lets the model reuse the same label across opinions in the same call, instead
    | of every opinion minting its own near-duplicate theme.
    |
    */
    'llm_batch_size' => (int) env('THEMES_LLM_BATCH_SIZE', 15),

    /*
    |--------------------------------------------------------------------------
    | Backfill Entity Cap
    |--------------------------------------------------------------------------
    |
    | Most-recent opinions processed per entity during batch backfill. Top themes
    | stabilize well before an entity's full opinion history is read, so this
    | bounds LLM cost without materially changing which themes surface.
    |
    */
    'backfill_entity_cap' => (int) env('THEMES_BACKFILL_ENTITY_CAP', 150),

    /*
    |--------------------------------------------------------------------------
    | Consolidation Limit
    |--------------------------------------------------------------------------
    |
    | Default max number of existing LLM themes (highest observation count
    | first) that themes:consolidate considers per run. Bounds cost on a
    | large, already-fragmented theme table; pass --limit=0 to process all.
    |
    */
    'consolidate_limit' => (int) env('THEMES_CONSOLIDATE_LIMIT', 400),

    /*
    |--------------------------------------------------------------------------
    | Empty State Copy
    |--------------------------------------------------------------------------
    |
    | Displayed when the entity or themes fall below configured thresholds.
    |
    */
    'empty_state_message' => 'Belum cukup opini untuk merangkum Suara Netijen.',

    /*
    |--------------------------------------------------------------------------
    | Sparse Themes Copy
    |--------------------------------------------------------------------------
    |
    | Displayed when the entity has enough opinions but no theme repeats often
    | enough to be shown.
    |
    */
    'sparse_state_message' => 'Opini netizen masih beragam, belum ada tema yang muncul berulang.',

];
