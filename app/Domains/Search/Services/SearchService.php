<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use Illuminate\Support\Facades\DB;
use Throwable;

class SearchService
{
    /**
     * Priority tier constants per docs/13 and docs/30.
     */
    public const PRIORITY_EXACT_NAME = 'exact_name';

    public const PRIORITY_EXACT_ALIAS = 'exact_alias';

    public const PRIORITY_PREFIX = 'prefix';

    public const PRIORITY_TRIGRAM = 'trigram';

    public const PRIORITY_CATEGORY_CONTEXT = 'category_context';

    public const PRIORITY_DESCRIPTOR = 'descriptor';

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

        // Tokenize and filter stopwords/years (docs/30)
        $rawTokens = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $maxTokens = (int) config('search.max_query_tokens', 8);
        $rawTokens = array_slice($rawTokens, 0, $maxTokens);

        $stopwords = config('search.stopwords', [
            'yang', 'dan', 'di', 'untuk', 'dengan', 'paling', 'terbaik', 'bagus', 'rekomendasi',
        ]);

        $filteredTokens = array_values(array_filter($rawTokens, function (string $token) use ($stopwords) {
            if (in_array($token, $stopwords, true)) {
                return false;
            }
            if (preg_match('/^\d{4}$/', $token)) {
                return false;
            }
            if (mb_strlen($token) < 2) {
                return false;
            }

            return true;
        }));

        // Empty after stopword removal falls back to browse mode (docs/30)
        if ($filteredTokens === []) {
            $results = $this->browseCandidates($category, $limit);

            return [
                'data' => $results,
                'meta' => [
                    'query' => $trimmedQuery,
                    'normalized_query' => $normalizedQuery,
                    'total' => count($results),
                ],
            ];
        }

        // Classify tokens into Anchors and Descriptors (docs/30)
        [$anchors, $descriptors] = $this->classifyTokens($filteredTokens);

        $results = $this->queryCandidates(
            $trimmedQuery,
            $normalizedQuery,
            $filteredTokens,
            $anchors,
            $descriptors,
            $category,
            $limit
        );

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
     * Classify query tokens into Anchors (matches name, alias, category) and Descriptors (rest).
     *
     * @param  list<string>  $tokens
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function classifyTokens(array $tokens): array
    {
        $anchors = [];
        $descriptors = [];

        foreach ($tokens as $token) {
            if ($this->isAnchorToken($token)) {
                $anchors[] = $token;
            } else {
                $descriptors[] = $token;
            }
        }

        return [$anchors, $descriptors];
    }

    /**
     * Check if a token matches any active searchable entity name, alias, or category.
     */
    protected function isAnchorToken(string $token): bool
    {
        $like = $this->wordPattern($token);
        $fuzzy = $this->allowsFuzzy($token);
        $activeEntityIds = DB::table('entities')->where('status', 'active')->where('searchable', true)->select('id');

        return DB::table('entities')
            ->where('status', 'active')
            ->where('searchable', true)
            ->where(function ($q) use ($like, $token, $fuzzy) {
                $q->whereRaw("(' ' || lower(name) || ' ') LIKE ?", [$like]);
                if ($fuzzy) {
                    $q->orWhereRaw('similarity(name, ?) >= 0.3', [$token]);
                }
            })
            ->exists()
            || DB::table('entity_aliases')
                ->whereIn('entity_id', $activeEntityIds)
                ->where(function ($q) use ($like, $token, $fuzzy) {
                    $q->whereRaw("(' ' || normalized_alias || ' ') LIKE ?", [$like]);
                    if ($fuzzy) {
                        $q->orWhereRaw('similarity(normalized_alias, ?) >= 0.3', [$token]);
                    }
                })
                ->exists()
            || DB::table('categories')
                ->where('status', 'active')
                ->whereRaw("(' ' || lower(name) || ' ') LIKE ?", [$like])
                ->exists();
    }

    /**
     * Word-start pattern for tokens of 4+ characters, whole-word for shorter ones, so "hp" never
     * anchors on the alias "hpm". Matched against ' ' || column || ' '.
     */
    protected function wordPattern(string $token): string
    {
        return $this->allowsFuzzy($token) ? '% '.$token.'%' : '% '.$token.' %';
    }

    /**
     * Trigram similarity is meaningless for very short tokens (2-3 characters share almost no trigrams).
     */
    protected function allowsFuzzy(string $token): bool
    {
        return mb_strlen($token) >= 4;
    }

    /**
     * Query and rank candidate entities using the 5 priority levels from docs/13
     * plus descriptor matching and sentiment tie-breaking from docs/30.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $anchors
     * @param  list<string>  $descriptors
     * @return list<array<string, mixed>>
     */
    protected function queryCandidates(
        string $rawQuery,
        string $normalizedQuery,
        array $tokens,
        array $anchors,
        array $descriptors,
        ?string $categorySlug,
        int $limit
    ): array {
        $prefix = $normalizedQuery.'%';

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

        // 1. Build descriptor scoring SQL (docs/30)
        $descriptorScoreParts = [];
        foreach ($descriptors as $idx => $desc) {
            $descThemeParam = 'desc_th_'.$idx;
            $descSpecParam = 'desc_sp_'.$idx;
            $descDescParam = 'desc_dc_'.$idx;
            $descSumParam = 'desc_sm_'.$idx;

            $boundaryToken = '% '.$desc.'%';
            $bindings[$descThemeParam] = $boundaryToken;
            $bindings[$descSpecParam] = $boundaryToken;
            $bindings[$descDescParam] = $boundaryToken;
            $bindings[$descSumParam] = $boundaryToken;

            $descriptorScoreParts[] = "(
                (CASE WHEN (' ' || lower(coalesce(d.theme_text, ''))) LIKE :{$descThemeParam} THEN
                    1000.0 * (1.0 + 0.2 * log(1.0 + coalesce((
                        SELECT MAX(ets.observation_count)
                        FROM entity_theme_snapshots ets
                        JOIN themes t ON t.id = ets.theme_id
                        WHERE ets.entity_id = e.id
                          AND ets.window IN ('365d', 'all')
                          AND (' ' || lower(t.display_label)) LIKE :{$descThemeParam}
                    ), 0.0)))
                ELSE 0.0 END) +
                (CASE WHEN (' ' || lower(coalesce(d.spec_text, ''))) LIKE :{$descSpecParam} THEN 500.0 ELSE 0.0 END) +
                (CASE WHEN (' ' || lower(coalesce(d.description_text, ''))) LIKE :{$descDescParam} THEN 200.0 ELSE 0.0 END) +
                (CASE WHEN (' ' || lower(coalesce(d.summary_text, ''))) LIKE :{$descSumParam} THEN 200.0 ELSE 0.0 END)
            )";
        }

        $rawDescriptorSql = $descriptorScoreParts !== [] ? implode(' + ', $descriptorScoreParts) : '0.0';
        // Bound descriptor score strictly below 4000 (context score) so it never overtakes name/alias/prefix matches
        $clampedDescriptorSql = "(CASE WHEN ({$rawDescriptorSql}) > 3900.0 THEN 3900.0 ELSE ({$rawDescriptorSql}) END)";

        $sql = "
            SELECT e.id, e.category_id, e.parent_id, e.type, e.name, e.slug, e.description,
                   c.name as category_name, c.slug as category_slug,
                   p.name as parent_name, p.slug as parent_slug,
                   d.theme_text, d.spec_text, d.description_text, d.summary_text,
                   {$clampedDescriptorSql} as descriptor_score,
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
            LEFT JOIN entity_search_documents d ON d.entity_id = e.id
            LEFT JOIN sentiment_snapshots ss_tie ON ss_tie.entity_id = e.id AND ss_tie.period = '365d'
            WHERE e.status = 'active' AND e.searchable = true
        ";

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = :filter_category';
            $bindings['filter_category'] = $categorySlug;
        }

        // 2. Candidate filter:
        if ($anchors === [] && $descriptors !== []) {
            // Case 3: Only Descriptors (e.g. "baterai awet") -> at least one descriptor matches document
            $descFilters = [];
            foreach ($descriptors as $idx => $desc) {
                $param = 'df_like_'.$idx;
                $bindings[$param] = '% '.$desc.'%';
                $descFilters[] = "(
                    (' ' || lower(coalesce(d.theme_text, ''))) LIKE :{$param}
                    OR (' ' || lower(coalesce(d.spec_text, ''))) LIKE :{$param}
                    OR (' ' || lower(coalesce(d.description_text, ''))) LIKE :{$param}
                    OR (' ' || lower(coalesce(d.summary_text, ''))) LIKE :{$param}
                )";
            }
            $sql .= ' AND ('.implode(' OR ', $descFilters).')';
        } else {
            // Case 1 & 2: Has Anchor(s)
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

            // When multiple anchors exist (or 1 anchor + descriptors), all anchors must match
            if (count($anchors) > 1 || (count($anchors) === 1 && count($descriptors) > 0)) {
                $anchorConditions = [];
                foreach ($anchors as $idx => $token) {
                    $tokenLikeParam = 't_like_'.$idx;
                    $bindings[$tokenLikeParam] = $this->wordPattern($token);

                    $simName = $simChild = $simParent = '';
                    if ($this->allowsFuzzy($token)) {
                        $tokenSimParam = 't_sim_'.$idx;
                        $bindings[$tokenSimParam] = $token;
                        $simName = " OR similarity(e.name, :{$tokenSimParam}) >= 0.3";
                        $simChild = " OR similarity(c_sub.name, :{$tokenSimParam}) >= 0.3";
                        $simParent = " OR similarity(p_sub.name, :{$tokenSimParam}) >= 0.3";
                    }

                    $anchorConditions[] = "(
                        (' ' || lower(e.name) || ' ') LIKE :{$tokenLikeParam}
                        OR EXISTS (SELECT 1 FROM entity_aliases ea WHERE ea.entity_id = e.id AND (' ' || ea.normalized_alias || ' ') LIKE :{$tokenLikeParam})
                        {$simName}
                        OR EXISTS (SELECT 1 FROM entities c_sub WHERE c_sub.parent_id = e.id AND ((' ' || lower(c_sub.name) || ' ') LIKE :{$tokenLikeParam}{$simChild}))
                        OR EXISTS (SELECT 1 FROM entities p_sub WHERE p_sub.id = e.parent_id AND ((' ' || lower(p_sub.name) || ' ') LIKE :{$tokenLikeParam}{$simParent}))
                        OR (' ' || lower(c.name) || ' ') LIKE :{$tokenLikeParam}
                    )";
                }
                $filterClauses[] = '('.implode(' AND ', $anchorConditions).')';
            }

            $sql .= ' AND ('.implode(' OR ', $filterClauses).')';
        }

        // Order by combined priority score descending, then sentiment tie-breaker, then name ascending
        $sql .= "
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
                END) +
                {$clampedDescriptorSql}
            ) DESC,
            (CASE WHEN ss_tie.opinion_count >= :tie_min_opinions THEN ss_tie.score ELSE 0 END) DESC,
            (CASE WHEN ss_tie.opinion_count >= :tie_min_opinions_count THEN ss_tie.opinion_count ELSE 0 END) DESC,
            e.name ASC
            LIMIT :query_limit
        ";

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
        $bindings['tie_min_opinions'] = (int) config('scoring.public_min_opinions', 30);
        $bindings['tie_min_opinions_count'] = (int) config('scoring.public_min_opinions', 30);
        $bindings['query_limit'] = $limit;

        $rawRows = DB::select($sql, $bindings);
        /** @var list<array<string, mixed>> $rows */
        $rows = array_map(fn (object $r): array => (array) $r, $rawRows);
        $publicData = $this->fetchPublicData(array_column($rows, 'id'));
        $matchedFields = $this->resolveMatchedFields(array_column($rows, 'id'), $descriptors, $rows);

        return array_map(
            fn (array $row): array => $this->mapRow(
                $row,
                $this->resolvePriorityTier($row, $normalizedQuery),
                $publicData[(int) $row['id']] ?? null,
                $matchedFields[(int) $row['id']] ?? []
            ),
            $rows
        );
    }

    /**
     * Resolve matched fields (theme labels, specs, description, summary) for returned candidates.
     *
     * @param  list<int>  $entityIds
     * @param  list<string>  $descriptors
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<string>>
     */
    protected function resolveMatchedFields(array $entityIds, array $descriptors, array $rows): array
    {
        if ($entityIds === [] || $descriptors === []) {
            return [];
        }

        $themeMatches = DB::table('entity_theme_snapshots as ets')
            ->join('themes as t', 't.id', '=', 'ets.theme_id')
            ->whereIn('ets.entity_id', $entityIds)
            ->whereIn('ets.window', [Period::OneYear->value, Period::All->value])
            ->where(function ($q) use ($descriptors) {
                foreach ($descriptors as $desc) {
                    $q->orWhereRaw("(' ' || lower(t.display_label)) LIKE ?", ['% '.$desc.'%']);
                }
            })
            ->orderByDesc('ets.observation_count')
            ->select(['ets.entity_id', 't.display_label'])
            ->get()
            ->reject(fn ($match) => EntitySearchDocumentBuilder::hasNegationMarker((string) $match->display_label))
            ->groupBy('entity_id');

        $rowsById = collect($rows)->keyBy(fn ($r) => (int) $r['id']);
        $result = [];

        foreach ($entityIds as $entityId) {
            $fields = [];
            $row = $rowsById->get($entityId);

            // 1. Theme matches
            if (isset($themeMatches[$entityId])) {
                foreach ($themeMatches[$entityId]->unique('display_label')->take(3) as $match) {
                    $fields[] = 'theme:'.$match->display_label;
                }
            }

            // 2. Spec matches
            $specText = ' '.strtolower((string) ($row['spec_text'] ?? ''));
            foreach ($descriptors as $desc) {
                if (str_contains($specText, ' '.$desc)) {
                    $fields[] = 'spec:'.$desc;
                }
            }

            // 3. Description matches
            $descText = ' '.strtolower((string) ($row['description_text'] ?? ''));
            foreach ($descriptors as $desc) {
                if (str_contains($descText, ' '.$desc)) {
                    $fields[] = 'description';
                    break;
                }
            }

            // 4. Summary matches
            $summaryText = ' '.strtolower((string) ($row['summary_text'] ?? ''));
            foreach ($descriptors as $desc) {
                if (str_contains($summaryText, ' '.$desc)) {
                    $fields[] = 'summary';
                    break;
                }
            }

            $result[$entityId] = array_values(array_unique($fields));
        }

        return $result;
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
        } else {
            // The unscoped browse is the indexable /search page: it must not link noindex (thin) entity pages.
            $query->whereIn('e.id', Entity::query()->publiclyEligible()->select('id'));
        }

        $rows = array_values($query->get()->map(fn (object $r): array => (array) $r)->all());
        $publicData = $this->fetchPublicData(array_column($rows, 'id'));

        return array_map(
            fn (array $row): array => $this->mapRow($row, ['tier' => self::PRIORITY_BROWSE, 'rank' => 0], $publicData[(int) $row['id']] ?? null, []),
            $rows
        );
    }

    /**
     * Batch-fetch each entity's Sentimen Netijen and Rating Netijen.
     *
     * @param  list<int>  $entityIds
     * @return array<int, array{score: float|null, opinion_count: int, rating: float|null, rating_count: int}>
     */
    private function fetchPublicData(array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
        if ($entityIds === []) {
            return [];
        }

        $snapshotsByEntity = SentimentSnapshot::query()
            ->whereIn('entity_id', $entityIds)
            ->whereIn('period', [Period::OneYear, Period::All])
            ->get()
            ->groupBy('entity_id');

        $ratingsByEntity = RatingSnapshot::query()
            ->whereIn('entity_id', $entityIds)
            ->get()
            ->keyBy('entity_id');

        $result = [];
        foreach ($entityIds as $entityId) {
            $snapshots = $snapshotsByEntity->get($entityId, collect())->keyBy(fn (SentimentSnapshot $s) => $s->period->value);
            $activeSnapshot = $snapshots->get(Period::OneYear->value) ?? $snapshots->get(Period::All->value);
            $opinionCount = $activeSnapshot ? (int) $activeSnapshot->opinion_count : 0;
            $isEligible = ScoreCalculator::isPublicScoreEligible($opinionCount);

            $rating = $ratingsByEntity->get($entityId);

            $result[$entityId] = [
                'score' => ($activeSnapshot && $isEligible) ? (float) $activeSnapshot->score : null,
                'opinion_count' => $opinionCount,
                'rating' => $rating?->rating_average !== null ? (float) $rating->rating_average : null,
                'rating_count' => $rating ? (int) $rating->rating_count : 0,
            ];
        }

        return $result;
    }

    /**
     * Shape a raw entity row into the public search-result array.
     *
     * @param  array<string, mixed>  $row
     * @param  array{tier: string, rank: int}  $priority
     * @param  array{score: float|null, opinion_count: int, rating: float|null, rating_count: int}|null  $publicData
     * @param  list<string>  $matchedFields
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $priority, ?array $publicData = null, array $matchedFields = []): array
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
            'score' => $publicData['score'] ?? null,
            'opinion_count' => $publicData['opinion_count'] ?? 0,
            'rating' => $publicData['rating'] ?? null,
            'rating_count' => $publicData['rating_count'] ?? 0,
            'priority_tier' => $priority['tier'],
            'priority_rank' => $priority['rank'],
            'match_detail' => isset($row['best_matching_alias']) && is_string($row['best_matching_alias']) ? $row['best_matching_alias'] : null,
            'matched_fields' => $matchedFields,
        ];
    }

    /**
     * Determine the matching priority tier per docs/13 and docs/30 specification.
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

        if ((int) ($row['context_score'] ?? 0) > 0) {
            return ['tier' => self::PRIORITY_CATEGORY_CONTEXT, 'rank' => 5];
        }

        if ((float) ($row['descriptor_score'] ?? 0.0) > 0) {
            return ['tier' => self::PRIORITY_DESCRIPTOR, 'rank' => 6];
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
