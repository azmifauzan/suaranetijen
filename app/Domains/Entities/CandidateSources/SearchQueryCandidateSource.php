<?php

namespace App\Domains\Entities\CandidateSources;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use App\Domains\Search\Models\SearchQuery;
use Illuminate\Support\Facades\DB;

/**
 * The primary candidate signal: normalized search queries that returned zero
 * results, grouped by normalized_query and ranked by how often they repeat.
 */
class SearchQueryCandidateSource implements EntityCandidateSource
{
    public function sourceType(): string
    {
        return 'search_query';
    }

    /**
     * @return list<array{raw_term: string, weight: int}>
     */
    public function discover(): array
    {
        $minFrequency = (int) config('entity_candidates.min_search_query_frequency', 3);

        $rows = SearchQuery::query()
            ->where('result_count', 0)
            ->select('normalized_query', DB::raw('count(*) as frequency'))
            ->groupBy('normalized_query')
            ->havingRaw('count(*) >= ?', [$minFrequency])
            ->get()
            ->map(fn (SearchQuery $row): array => [
                'raw_term' => $row->normalized_query,
                'weight' => (int) $row->getAttribute('frequency'),
            ])
            ->all();

        return array_values($rows);
    }
}
