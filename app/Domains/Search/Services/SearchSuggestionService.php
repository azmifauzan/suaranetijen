<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;

class SearchSuggestionService
{
    /**
     * Return a balanced mix of popular searches and entities with the highest
     * eligible public sentiment scores.
     *
     * @return list<array{query: string, source: 'trending'|'top_score'}>
     */
    public function getSuggestions(int $limit = 4): array
    {
        $limit = min(max($limit, 1), 10);
        $trendingQueries = $this->getTrendingQueries($limit);
        $topScoreEntities = $this->getTopScoreEntities($limit);
        $suggestions = [];
        $seenQueries = [];

        for ($index = 0; $index < max(count($trendingQueries), count($topScoreEntities)); $index++) {
            foreach ([$trendingQueries[$index] ?? null, $topScoreEntities[$index] ?? null] as $suggestion) {
                if ($suggestion === null) {
                    continue;
                }

                $normalizedQuery = TextNormalizer::normalize($suggestion['query']);

                if ($normalizedQuery === '' || isset($seenQueries[$normalizedQuery])) {
                    continue;
                }

                $suggestions[] = $suggestion;
                $seenQueries[$normalizedQuery] = true;

                if (count($suggestions) === $limit) {
                    break 2;
                }
            }
        }

        return $suggestions;
    }

    /**
     * @return list<array{query: string, source: 'trending'}>
     */
    protected function getTrendingQueries(int $limit): array
    {
        return array_values(SearchQuery::query()
            ->where('result_count', '>', 0)
            ->whereNotNull('normalized_query')
            ->whereRaw('length(normalized_query) between 2 and 80')
            ->select('normalized_query')
            ->selectRaw('COUNT(*) as search_count')
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->orderBy('normalized_query')
            ->limit($limit)
            ->get()
            ->map(fn (SearchQuery $searchQuery): array => [
                'query' => $searchQuery->normalized_query,
                'source' => 'trending',
            ])
            ->all());
    }

    /**
     * @return list<array{query: string, source: 'top_score'}>
     */
    protected function getTopScoreEntities(int $limit): array
    {
        return array_values(SentimentSnapshot::query()
            ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
            ->where('sentiment_snapshots.period', Period::OneYear->value)
            ->where('sentiment_snapshots.opinion_count', '>=', (int) config('scoring.public_min_opinions'))
            ->whereNotNull('sentiment_snapshots.score')
            ->where('entities.status', EntityStatus::Active)
            ->where('entities.searchable', true)
            ->orderByDesc('sentiment_snapshots.score')
            ->orderByDesc('sentiment_snapshots.opinion_count')
            ->orderBy('entities.name')
            ->select('sentiment_snapshots.*')
            ->with('entity')
            ->limit($limit)
            ->get()
            ->map(fn (SentimentSnapshot $snapshot): array => [
                'query' => $snapshot->entity->name,
                'source' => 'top_score',
            ])
            ->all());
    }
}
