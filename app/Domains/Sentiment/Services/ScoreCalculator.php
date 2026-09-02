<?php

namespace App\Domains\Sentiment\Services;

class ScoreCalculator
{
    /**
     * Compute Sentimen Netijen score (0 - 100) using Formula v1:
     * score = 100 * (positive + 0.5 * neutral) / total
     *
     * Equivalent to positive=100, neutral=50, negative=0.
     */
    public static function calculate(int $positive, int $neutral, int $negative): ?float
    {
        $total = $positive + $neutral + $negative;

        if ($total <= 0) {
            return null;
        }

        $rawScore = 100.0 * ($positive + 0.5 * $neutral) / $total;

        return round($rawScore, 2);
    }

    /**
     * Current score formula version, stamped on every snapshot (`config/scoring.php`).
     */
    public static function formulaVersion(): string
    {
        return (string) config('scoring.formula_version');
    }

    /**
     * Minimum opinion count for a public score, `null` reads the configured default
     * (`config/scoring.php`, `examples/score-config.yaml`).
     */
    public static function isPublicScoreEligible(int $opinionCount, ?int $minOpinions = null): bool
    {
        return $opinionCount >= ($minOpinions ?? (int) config('scoring.public_min_opinions'));
    }

    /**
     * Minimum opinion count for ranking eligibility, `null` reads the configured default
     * (`config/scoring.php`, `examples/score-config.yaml`).
     */
    public static function isRankingEligible(int $opinionCount, ?int $minOpinions = null): bool
    {
        return $opinionCount >= ($minOpinions ?? (int) config('scoring.ranking_min_opinions'));
    }
}
