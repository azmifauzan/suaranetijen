<?php

namespace App\Domains\Sentiment\Services;

use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SentimentAggregator
{
    /**
     * Compute and persist daily sentiment aggregate for an entity on a specific date.
     */
    public function aggregateDaily(int $entityId, CarbonInterface $date): SentimentDaily
    {
        $dateStr = $date->format('Y-m-d');
        $startOfDay = $date->startOfDay();
        $endOfDay = $date->endOfDay();

        $observations = SentimentObservation::query()
            ->where('entity_id', $entityId)
            ->whereBetween('observed_at', [$startOfDay, $endOfDay])
            ->select('sentiment', DB::raw('count(*) as count'))
            ->groupBy('sentiment')
            ->pluck('count', 'sentiment');

        $positive = (int) ($observations[SentimentClass::Positive->value] ?? 0);
        $neutral = (int) ($observations[SentimentClass::Neutral->value] ?? 0);
        $negative = (int) ($observations[SentimentClass::Negative->value] ?? 0);
        $total = $positive + $neutral + $negative;

        $score = ScoreCalculator::calculate($positive, $neutral, $negative);

        return SentimentDaily::updateOrCreate(
            ['entity_id' => $entityId, 'date' => $dateStr],
            [
                'positive_count' => $positive,
                'neutral_count' => $neutral,
                'negative_count' => $negative,
                'opinion_count' => $total,
                'score' => $score,
            ]
        );
    }

    /**
     * Compute and persist a sentiment snapshot for an entity across a defined period.
     */
    public function aggregateSnapshot(
        int $entityId,
        Period $period,
        ?CarbonInterface $now = null,
        ?int $minOpinions = null
    ): SentimentSnapshot {
        $minOpinions ??= (int) config('scoring.public_min_opinions');
        $referenceNow = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();

        $query = SentimentObservation::query()->where('entity_id', $entityId);

        $days = $period->days();
        if ($days !== null) {
            $lookbackDate = $referenceNow->subDays($days)->startOfDay();
            $query->where('observed_at', '>=', $lookbackDate);
        }

        $counts = $query->select('sentiment', DB::raw('count(*) as count'))
            ->groupBy('sentiment')
            ->pluck('count', 'sentiment');

        $positive = (int) ($counts[SentimentClass::Positive->value] ?? 0);
        $neutral = (int) ($counts[SentimentClass::Neutral->value] ?? 0);
        $negative = (int) ($counts[SentimentClass::Negative->value] ?? 0);
        $total = $positive + $neutral + $negative;

        // Score is computed only if total opinions meets minimum threshold
        $score = $total >= $minOpinions
            ? ScoreCalculator::calculate($positive, $neutral, $negative)
            : null;

        return SentimentSnapshot::updateOrCreate(
            ['entity_id' => $entityId, 'period' => $period->value],
            [
                'positive_count' => $positive,
                'neutral_count' => $neutral,
                'negative_count' => $negative,
                'opinion_count' => $total,
                'score' => $score,
                'sentiment_model_version' => 'v1',
                'score_formula_version' => ScoreCalculator::formulaVersion(),
                'calculated_at' => $referenceNow,
            ]
        );
    }

    /**
     * Refresh all four period snapshots (30d, 90d, 365d, all) for an entity.
     *
     * @return array<string, SentimentSnapshot>
     */
    public function refreshAllSnapshots(int $entityId, ?CarbonInterface $now = null): array
    {
        $snapshots = [];
        foreach (Period::cases() as $period) {
            $snapshots[$period->value] = $this->aggregateSnapshot($entityId, $period, $now);
        }

        return $snapshots;
    }
}
