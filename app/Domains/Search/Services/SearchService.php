<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Models\SearchQuery;
use Illuminate\Support\Facades\DB;
use Throwable;

class SearchService
{
    /**
     * Priority tier constants.
     */
    public const PRIORITY_EXACT_NAME = 'exact_name';

    public const PRIORITY_EXACT_ALIAS = 'exact_alias';

    public const PRIORITY_PREFIX = 'prefix';

    public const PRIORITY_TRIGRAM = 'trigram';

    public const PRIORITY_CATEGORY_CONTEXT = 'category_context';

    public const PRIORITY_BROWSE = 'browse';

    /**
     * Execute a search query across active searchable entities.
     *
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{
     *         query: string,
     *         normalized_query: string,
     *         total: int
     *     }
     * }
     */
    public function search(
        string $query,
        ?string $category = null,
        int $limit = 20,
        ?int $userId = null,
        ?string $sessionId = null,
        bool $logQuery = true
    ): array {
        $trimmedQuery = trim($query);
        $normalizedQuery = TextNormalizer::normalize($trimmedQuery);

        if ($normalizedQuery === '') {
            // No search text: browse (optionally category-scoped) instead of returning nothing,
            // so "Semua Entitas" / a homepage category card has content to land on.
            $results = $this->browseCandidates($category, $limit);

            return [
                'data' => $results,
                'meta' => [
                    'query' => $trimmedQuery,
                    'normalized_query' => '',
                    'total' => count($results),
                ],
            ];
        }

        $tokens = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $results = $this->queryCandidates($trimmedQuery, $normalizedQuery, $tokens, $category, $limit);

        if ($logQuery) {
            $this->logSearch($trimmedQuery, $normalizedQuery, count($results), $userId, $sessionId);
        }

        return [
            'data' => $results,
            'meta' => [
                'query' => $trimmedQuery,
                'normalized_query' => $normalizedQuery,
                'total' => count($results),
            ],
        ];
    }

    /**
     * Query and rank candidate entities using the 5 priority levels from docs/13.
     *
     * @param  list<string>  $tokens
     * @return list<array<string, mixed>>
     */
    protected function queryCandidates(
        string $rawQuery,
        string $normalizedQuery,
        array $tokens,
        ?string $categorySlug,
        int $limit
    ): array {
        $prefix = $normalizedQuery.'%';

        $sql = "
            SELECT e.id, e.category_id, e.parent_id, e.type, e.name, e.slug, e.description,
                   c.name as category_name, c.slug as category_slug,
                   p.name as parent_name, p.slug as parent_slug,
                   (CASE WHEN lower(e.name) = :exact_name THEN 100000 ELSE 0 END) as exact_name_score,
                   (CASE WHEN EXISTS (
                       SELECT 1 FROM entity_aliases ea
                       WHERE ea.entity_id = e.id AND ea.normalized_alias = :exact_alias
                   ) THEN 80000 ELSE 0 END) as exact_alias_score,
                   (CASE
                       WHEN lower(e.name) LIKE :prefix_name THEN 60000
                       WHEN EXISTS (
                           SELECT 1 FROM entity_aliases ea
                           WHERE ea.entity_id = e.id AND ea.normalized_alias LIKE :prefix_alias
                       ) THEN 50000
                       ELSE 0
                   END) as prefix_score,
                   greatest(
                       similarity(e.name, :sim_query1),
                       coalesce((
                           SELECT max(similarity(ea.normalized_alias, :sim_query2))
                           FROM entity_aliases ea
                           WHERE ea.entity_id = e.id
                       ), 0)
                   ) as trgm_sim,
                   (
                       SELECT ea.alias FROM entity_aliases ea
                       WHERE ea.entity_id = e.id
                       ORDER BY (
                           (CASE WHEN ea.normalized_alias = :best_exact THEN 100000 ELSE 0 END) +
                           (CASE WHEN ea.normalized_alias LIKE :best_prefix THEN 50000 ELSE 0 END) +
                           (similarity(ea.normalized_alias, :best_sim) * 20000)
                       ) DESC
                       LIMIT 1
                   ) as best_matching_alias,
                   (CASE
                       WHEN lower(c.name) LIKE :cat_prefix OR lower(c.slug) = :cat_slug THEN 5000
                       WHEN EXISTS (
                           SELECT 1 FROM entities child
                           WHERE child.parent_id = e.id AND (
                               lower(child.name) LIKE :child_prefix
                               OR similarity(child.name, :child_sim) >= 0.3
                           )
                       ) THEN 5000
                       WHEN p.id IS NOT NULL AND (
                           lower(p.name) LIKE :parent_prefix
                           OR similarity(p.name, :parent_sim) >= 0.3
                       ) THEN 4000
                       ELSE 0
                   END) as context_score
            FROM entities e
            JOIN categories c ON c.id = e.category_id
            LEFT JOIN entities p ON p.id = e.parent_id
            WHERE e.status = 'active' AND e.searchable = true
        ";

        $bindings = [
            'exact_name' => $normalizedQuery,
            'exact_alias' => $normalizedQuery,
            'prefix_name' => $prefix,
            'prefix_alias' => $prefix,
            'sim_query1' => $normalizedQuery,
            'sim_query2' => $normalizedQuery,
            'best_exact' => $normalizedQuery,
            'best_prefix' => $prefix,
            'best_sim' => $normalizedQuery,
            'cat_prefix' => $prefix,
            'cat_slug' => $normalizedQuery,
            'child_prefix' => $prefix,
            'child_sim' => $normalizedQuery,
            'parent_prefix' => $prefix,
            'parent_sim' => $normalizedQuery,
        ];

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = :filter_category';
            $bindings['filter_category'] = $categorySlug;
        }

        // Candidate filter: candidate must meet at least one matching criterion
        $filterClauses = [
            'lower(e.name) = :f_exact',
            'EXISTS (SELECT 1 FROM entity_aliases ea WHERE ea.entity_id = e.id AND ea.normalized_alias = :f_exact_alias)',
            'lower(e.name) LIKE :f_prefix',
            'EXISTS (SELECT 1 FROM entity_aliases ea WHERE ea.entity_id = e.id AND ea.normalized_alias LIKE :f_prefix_alias)',
            'similarity(e.name, :f_sim1) >= 0.25',
            'EXISTS (SELECT 1 FROM entity_aliases ea WHERE ea.entity_id = e.id AND similarity(ea.normalized_alias, :f_sim2) >= 0.25)',
            'EXISTS (SELECT 1 FROM entities child WHERE child.parent_id = e.id AND (similarity(child.name, :f_child_sim) >= 0.3 OR lower(child.name) LIKE :f_child_prefix))',
            'EXISTS (SELECT 1 FROM entities parent_e WHERE parent_e.id = e.parent_id AND (similarity(parent_e.name, :f_parent_sim) >= 0.3 OR lower(parent_e.name) LIKE :f_parent_prefix))',
        ];

        $bindings['f_exact'] = $normalizedQuery;
        $bindings['f_exact_alias'] = $normalizedQuery;
        $bindings['f_prefix'] = $prefix;
        $bindings['f_prefix_alias'] = $prefix;
        $bindings['f_sim1'] = $normalizedQuery;
        $bindings['f_sim2'] = $normalizedQuery;
        $bindings['f_child_sim'] = $normalizedQuery;
        $bindings['f_child_prefix'] = $prefix;
        $bindings['f_parent_sim'] = $normalizedQuery;
        $bindings['f_parent_prefix'] = $prefix;

        // Add token-based matching clauses for multi-word queries (e.g. "vps biznet")
        if (count($tokens) > 1) {
            $tokenConditions = [];
            foreach ($tokens as $idx => $token) {
                $tokenLikeParam = 't_like_'.$idx;
                $tokenSimParam = 't_sim_'.$idx;

                $bindings[$tokenLikeParam] = '%'.$token.'%';
                $bindings[$tokenSimParam] = $token;

                $tokenConditions[] = "(
                    lower(e.name) LIKE :{$tokenLikeParam}
                    OR EXISTS (SELECT 1 FROM entity_aliases ea WHERE ea.entity_id = e.id AND ea.normalized_alias LIKE :{$tokenLikeParam})
                    OR similarity(e.name, :{$tokenSimParam}) >= 0.3
                    OR EXISTS (SELECT 1 FROM entities c_sub WHERE c_sub.parent_id = e.id AND (lower(c_sub.name) LIKE :{$tokenLikeParam} OR similarity(c_sub.name, :{$tokenSimParam}) >= 0.3))
                    OR EXISTS (SELECT 1 FROM entities p_sub WHERE p_sub.id = e.parent_id AND (lower(p_sub.name) LIKE :{$tokenLikeParam} OR similarity(p_sub.name, :{$tokenSimParam}) >= 0.3))
                    OR lower(c.name) LIKE :{$tokenLikeParam}
                )";
            }
            $filterClauses[] = '('.implode(' AND ', $tokenConditions).')';
        }

        $sql .= ' AND ('.implode(' OR ', $filterClauses).')';

        // Order by combined priority score descending, then name ascending
        $sql .= '
            ORDER BY (
                (CASE WHEN lower(e.name) = :ord_exact THEN 100000 ELSE 0 END) +
                (CASE WHEN EXISTS (
                    SELECT 1 FROM entity_aliases ea
                    WHERE ea.entity_id = e.id AND ea.normalized_alias = :ord_exact_alias
                ) THEN 80000 ELSE 0 END) +
                (CASE
                    WHEN lower(e.name) LIKE :ord_prefix_name THEN 60000
                    WHEN EXISTS (
                        SELECT 1 FROM entity_aliases ea
                        WHERE ea.entity_id = e.id AND ea.normalized_alias LIKE :ord_prefix_alias
                    ) THEN 50000
                    ELSE 0
                END) +
                (greatest(
                    similarity(e.name, :ord_sim1),
                    coalesce((
                        SELECT max(similarity(ea.normalized_alias, :ord_sim2))
                        FROM entity_aliases ea
                        WHERE ea.entity_id = e.id
                    ), 0)
                ) * 20000) +
                (CASE
                    WHEN lower(c.name) LIKE :ord_cat_prefix OR lower(c.slug) = :ord_cat_slug THEN 5000
                    WHEN EXISTS (
                        SELECT 1 FROM entities child
                        WHERE child.parent_id = e.id AND (
                            lower(child.name) LIKE :ord_child_prefix
                            OR similarity(child.name, :ord_child_sim) >= 0.3
                        )
                    ) THEN 5000
                    WHEN p.id IS NOT NULL AND (
                        lower(p.name) LIKE :ord_parent_prefix
                        OR similarity(p.name, :ord_parent_sim) >= 0.3
                    ) THEN 4000
                    ELSE 0
                END)
            ) DESC, e.name ASC
            LIMIT :query_limit
        ';

        $bindings['ord_exact'] = $normalizedQuery;
        $bindings['ord_exact_alias'] = $normalizedQuery;
        $bindings['ord_prefix_name'] = $prefix;
        $bindings['ord_prefix_alias'] = $prefix;
        $bindings['ord_sim1'] = $normalizedQuery;
        $bindings['ord_sim2'] = $normalizedQuery;
        $bindings['ord_cat_prefix'] = $prefix;
        $bindings['ord_cat_slug'] = $normalizedQuery;
        $bindings['ord_child_prefix'] = $prefix;
        $bindings['ord_child_sim'] = $normalizedQuery;
        $bindings['ord_parent_prefix'] = $prefix;
        $bindings['ord_parent_sim'] = $normalizedQuery;
        $bindings['query_limit'] = $limit;

        $rawRows = DB::select($sql, $bindings);
        /** @var list<array<string, mixed>> $rows */
        $rows = array_map(fn (object $r): array => (array) $r, $rawRows);

        return array_map(
            fn (array $row): array => $this->mapRow($row, $this->resolvePriorityTier($row, $normalizedQuery)),
            $rows
        );
    }

    /**
     * List entities with no search text, optionally scoped to a category, ordered by name.
     * Backs the "Semua Entitas" / category-card browse flow, which has no query to rank by.
     *
     * @return list<array<string, mixed>>
     */
    protected function browseCandidates(?string $categorySlug, int $limit): array
    {
        $query = DB::table('entities as e')
            ->join('categories as c', 'c.id', '=', 'e.category_id')
            ->leftJoin('entities as p', 'p.id', '=', 'e.parent_id')
            ->where('e.status', 'active')
            ->where('e.searchable', true)
            ->orderBy('e.name')
            ->limit($limit)
            ->select([
                'e.id', 'e.category_id', 'e.parent_id', 'e.type', 'e.name', 'e.slug', 'e.description',
                'c.name as category_name', 'c.slug as category_slug',
                'p.name as parent_name', 'p.slug as parent_slug',
            ]);

        if ($categorySlug !== null && $categorySlug !== '') {
            $query->where('c.slug', $categorySlug);
        }

        $rows = array_values($query->get()->map(fn (object $r): array => (array) $r)->all());

        return array_map(
            fn (array $row): array => $this->mapRow($row, ['tier' => self::PRIORITY_BROWSE, 'rank' => 0]),
            $rows
        );
    }

    /**
     * Shape a raw entity row (from either the ranked search or the plain browse query) into the
     * public search-result array.
     *
     * @param  array<string, mixed>  $row
     * @param  array{tier: string, rank: int}  $priority
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $priority): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'type' => (string) $row['type'],
            'type_label' => ucfirst((string) $row['type']),
            'description' => isset($row['description']) && is_string($row['description']) ? $row['description'] : null,
            'category' => [
                'id' => (int) $row['category_id'],
                'name' => (string) $row['category_name'],
                'slug' => (string) $row['category_slug'],
            ],
            'parent' => isset($row['parent_id']) ? [
                'id' => (int) $row['parent_id'],
                'name' => (string) $row['parent_name'],
                'slug' => (string) $row['parent_slug'],
            ] : null,
            'url' => '/e/'.$row['slug'],
            'score' => null, // Sentimen Netijen (null for MVP until Epic 8)
            'opinion_count' => 0,
            'rating' => null,
            'rating_count' => 0,
            'priority_tier' => $priority['tier'],
            'priority_rank' => $priority['rank'],
            'match_detail' => isset($row['best_matching_alias']) && is_string($row['best_matching_alias']) ? $row['best_matching_alias'] : null,
        ];
    }

    /**
     * Determine the matching priority tier per docs/13 specification.
     *
     * @param  array<string, mixed>  $row
     * @return array{tier: string, rank: int}
     */
    protected function resolvePriorityTier(array $row, string $normalizedQuery): array
    {
        if ((int) ($row['exact_name_score'] ?? 0) > 0) {
            return ['tier' => self::PRIORITY_EXACT_NAME, 'rank' => 1];
        }

        if ((int) ($row['exact_alias_score'] ?? 0) > 0) {
            return ['tier' => self::PRIORITY_EXACT_ALIAS, 'rank' => 2];
        }

        if ((int) ($row['prefix_score'] ?? 0) > 0) {
            return ['tier' => self::PRIORITY_PREFIX, 'rank' => 3];
        }

        if ((float) ($row['trgm_sim'] ?? 0.0) >= 0.25) {
            return ['tier' => self::PRIORITY_TRIGRAM, 'rank' => 4];
        }

        return ['tier' => self::PRIORITY_CATEGORY_CONTEXT, 'rank' => 5];
    }

    /**
     * Log the search query for the zero-result growth loop and analytics.
     */
    protected function logSearch(
        string $rawQuery,
        string $normalizedQuery,
        int $resultCount,
        ?int $userId = null,
        ?string $sessionId = null
    ): void {
        try {
            SearchQuery::create([
                'query' => $rawQuery,
                'normalized_query' => $normalizedQuery,
                'result_count' => $resultCount,
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
        } catch (Throwable) {
            // Failure to log a search query must not fail the search request
        }
    }
}
