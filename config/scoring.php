<?php

// Mirrors examples/score-config.yaml (docs/11). Override via env for a rollout without a deploy;
// changing the numbers here is a scoring-policy decision, not a code change.

return [

    'formula_version' => env('SCORING_FORMULA_VERSION', 'v1'),

    'public_min_opinions' => (int) env('SCORING_PUBLIC_MIN_OPINIONS', 30),

    'ranking_min_opinions' => (int) env('SCORING_RANKING_MIN_OPINIONS', 100),

];
