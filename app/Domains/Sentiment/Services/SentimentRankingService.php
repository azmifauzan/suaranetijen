<?php

namespace App\Domains\Sentiment\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Illuminate\Support\Collection;

class SentimentRankingService
{
    /**
     * Get ranked entities for a category (or all categories) within a period.
     *
     * Ranking rule from docs/11:
     * 1. score DESC
     * 2. opinion_count DESC
     * 3. name ASC (deterministic fallback)
     *
     * Threshold: opinion_count >= ranking_min_opinions (default 100).
     *
     * @return Collection<int, array{
     *     rank: int,
     *     entity: Entity,
     *     score: float,
     *     opinion_count: int,
     *     positive_count: int,
     *     neutral_count: int,
     *     negative_count: int,
     *     period: '30d'|'90d'|'365d'|'all'
     * }>
     */
    public function getRanking(
        ?int $categoryId = null,
        Period $period = Period::OneYear,
        ?int $minOpinions = null,
        int $limit = 50
    ): Collection {
        $minOpinions ??= (int) config('scoring.ranking_min_opinions');

        $query = SentimentSnapshot::query()
            ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
            ->where('sentiment_snapshots.period', $period->value)
            ->where('sentiment_snapshots.opinion_count', '>=', $minOpinions)
            ->whereNotNull('sentiment_snapshots.score')
            ->where('entities.status', EntityStatus::Active)
            ->where('entities.rankable', true);

        if ($categoryId !== null) {
            $query->where('entities.category_id', $categoryId);
        }

        // docs/11: 1. score desc, 2. opinion_count desc, 3. name asc
        $snapshots = $query
            ->orderByDesc('sentiment_snapshots.score')
            ->orderByDesc('sentiment_snapshots.opinion_count')
            ->orderBy('entities.name', 'asc')
            ->select('sentiment_snapshots.*')
            ->with(['entity.category'])
            ->limit($limit)
            ->get();

        $rank = 1;

        return $snapshots->map(function (SentimentSnapshot $snap) use (&$rank): array {
            return [
                'rank' => (int) $rank++,
                'entity' => $snap->entity,
                'score' => (float) $snap->score,
                'opinion_count' => (int) $snap->opinion_count,
                'positive_count' => (int) $snap->positive_count,
                'neutral_count' => (int) $snap->neutral_count,
                'negative_count' => (int) $snap->negative_count,
                'period' => $snap->period->value,
            ];
        });
    }
}
