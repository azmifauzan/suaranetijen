<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Illuminate\Support\Facades\Cache;

class SearchSuggestionService
{
    public function __construct(
        protected CandidateScannerService $safetyFilter
    ) {}

    /**
     * Keywords many different visitors searched recently, topped up with the entities of
     * highest public sentiment only while there are fewer keywords than slots.
     *
     * @return list<array{query: string, source: 'trending'|'top_score'}>
     */
    public function getSuggestions(?int $limit = null): array
    {
        $limit = min(max($limit ?? (int) config('search.suggestions.limit', 6), 1), 10);

        $suggestions = array_slice($this->getTrendingQueries(), 0, $limit);
        $seen = array_map(fn (array $s): string => TextNormalizer::normalize($s['query']), $suggestions);

        if (count($suggestions) < $limit) {
            foreach ($this->getTopScoreEntities($limit) as $suggestion) {
                $normalized = TextNormalizer::normalize($suggestion['query']);

                if ($normalized === '' || in_array($normalized, $seen, true)) {
                    continue;
                }

                $suggestions[] = $suggestion;
                $seen[] = $normalized;

                if (count($suggestions) === $limit) {
                    break;
                }
            }
        }

        return $suggestions;
    }

    /**
     * @return array<int, array{query: string, source: 'trending'}>
     */
    protected function getTrendingQueries(): array
    {
        return Cache::remember(
            'search:suggestions:trending',
            (int) config('search.suggestions.cache_seconds', 3600),
            fn (): array => $this->queryTrending()
        );
    }

    /**
     * @return array<int, array{query: string, source: 'trending'}>
     */
    private function queryTrending(): array
    {
        $visitors = SearchQuery::visitorSql();
        $minSessions = (int) config('search.suggestions.min_sessions', 3);
        $publicFigureTerms = $this->safetyFilter->getPublicFigureTerms();

        $rows = SearchQuery::query()
            ->where('created_at', '>=', now()->subDays((int) config('search.suggestions.window_days', 30)))
            ->where('result_count', '>', 0)
            ->whereNotNull('normalized_query')
            ->whereRaw('length(normalized_query) between 2 and 80')
            ->select('normalized_query')
            ->selectRaw("COUNT(DISTINCT {$visitors}) as visitor_count")
            ->groupBy('normalized_query')
            ->havingRaw("COUNT(DISTINCT {$visitors}) >= ?", [$minSessions])
            ->orderByDesc('visitor_count')
            ->orderBy('normalized_query')
            ->limit(60)
            ->pluck('normalized_query');

        $suggestions = [];

        foreach ($rows as $query) {
            if ($this->safetyFilter->isSafe($query) && ! $this->safetyFilter->mentionsAny($query, $publicFigureTerms)) {
                $suggestions[] = ['query' => $query, 'source' => 'trending'];
            }
        }

        return $suggestions;
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
