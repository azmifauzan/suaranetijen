<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicEntityList;
use App\Domains\Sentiment\Enums\Period;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomepageCategoryBlockService
{
    public const CACHE_KEY_BLOCKS = 'homepage:category_blocks';

    public const CACHE_KEY_POPULAR_TOPICS = 'homepage:popular_topics';

    public const CACHE_TTL_SECONDS = 900; // 15 minutes

    public function __construct(
        protected TopicEntityList $topicEntityList
    ) {}

    /**
     * Get the 11 root category blocks with their top 3 entities, child categories, and topic links.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_public_figure: bool,
     *     top_entities: array<int, array{
     *         id: int,
     *         name: string,
     *         slug: string,
     *         score: float,
     *         opinion_count: int,
     *         category_name: string,
     *         category_slug: string
     *     }>,
     *     child_categories: array<int, array{
     *         id: int,
     *         name: string,
     *         slug: string
     *     }>,
     *     topics: array<int, array{
     *         id: int,
     *         slug: string,
     *         title: string,
     *         keyword: string
     *     }>,
     *     category_url: string,
     *     top_ranking_url: string
     * }>
     */
    public function getCategoryBlocks(): array
    {
        return Cache::remember(self::CACHE_KEY_BLOCKS, self::CACHE_TTL_SECONDS, function () {
            // 1. Fetch active root categories with active child subcategories
            $rootCategories = Category::query()
                ->active()
                ->whereNull('parent_id')
                ->with([
                    'children' => fn ($query) => $query->active()->orderBy('name'),
                ])
                ->orderBy('name')
                ->get();

            if ($rootCategories->isEmpty()) {
                return [];
            }

            // Collect all category IDs (roots + children) and build a mapping to their root category ID
            $categoryToRootMap = [];
            $allCategoryIds = [];

            foreach ($rootCategories as $root) {
                $categoryToRootMap[$root->id] = $root->id;
                $allCategoryIds[] = $root->id;

                foreach ($root->children as $child) {
                    $categoryToRootMap[$child->id] = $root->id;
                    $allCategoryIds[] = $child->id;
                }
            }

            $tokohPublikId = Category::where('slug', 'tokoh-publik')->value('id');
            $minOpinions = (int) config('scoring.public_min_opinions', 30);

            // 2. Fetch Top 3 entities per root category across child categories (and direct entities)
            // Using a single SQL query with window function:
            // ROW_NUMBER() OVER (PARTITION BY root_category_id ORDER BY score DESC, opinion_count DESC, name ASC)
            $rankedSubquery = DB::table('sentiment_snapshots')
                ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
                ->join('categories', 'categories.id', '=', 'entities.category_id')
                ->where('entities.status', EntityStatus::Active->value)
                ->where('entities.searchable', true)
                ->where('categories.status', CategoryStatus::Active->value)
                ->where('sentiment_snapshots.period', Period::OneYear->value)
                ->where('sentiment_snapshots.opinion_count', '>=', $minOpinions)
                ->whereNotNull('sentiment_snapshots.score')
                ->when($tokohPublikId, function ($query) use ($tokohPublikId) {
                    $query->whereRaw('COALESCE(categories.parent_id, categories.id) != ?', [$tokohPublikId]);
                })
                ->selectRaw('
                    entities.id,
                    entities.name,
                    entities.slug,
                    categories.name as category_name,
                    categories.slug as category_slug,
                    COALESCE(categories.parent_id, categories.id) as root_category_id,
                    sentiment_snapshots.score,
                    sentiment_snapshots.opinion_count,
                    ROW_NUMBER() OVER (
                        PARTITION BY COALESCE(categories.parent_id, categories.id)
                        ORDER BY sentiment_snapshots.score DESC, sentiment_snapshots.opinion_count DESC, entities.name ASC
                    ) as row_num
                ');

            $topEntitiesRows = DB::query()
                ->fromSub($rankedSubquery, 'ranked')
                ->where('row_num', '<=', 3)
                ->get();

            $groupedEntities = $topEntitiesRows->groupBy('root_category_id');

            // 3. Fetch published and indexable topics for these categories
            $publishedTopics = SearchLandingPage::query()
                ->published()
                ->whereIn('category_id', $allCategoryIds)
                ->with(['category.parent', 'themes'])
                ->orderByDesc('candidate_signal')
                ->orderBy('title')
                ->get();

            $indexableTopics = $publishedTopics->filter(fn (SearchLandingPage $t) => $this->topicEntityList->isIndexable($t));

            // Group topics by their root category ID
            $groupedTopics = $indexableTopics->groupBy(function ($topic) use ($categoryToRootMap) {
                return $categoryToRootMap[$topic->category_id] ?? 0;
            });

            // 4. Assemble blocks
            return $rootCategories->map(function (Category $root) use ($groupedEntities, $groupedTopics) {
                $isPublicFigure = ($root->slug === 'tokoh-publik');

                $entities = $isPublicFigure
                    ? []
                    : ($groupedEntities->get($root->id, collect()))->map(function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'name' => (string) $row->name,
                            'slug' => (string) $row->slug,
                            'score' => (float) $row->score,
                            'opinion_count' => (int) $row->opinion_count,
                            'category_name' => (string) $row->category_name,
                            'category_slug' => (string) $row->category_slug,
                        ];
                    })->values()->all();

                $categoryTopics = ($groupedTopics->get($root->id, collect()))
                    ->take(3)
                    ->map(function (SearchLandingPage $topic) {
                        return [
                            'id' => (int) $topic->id,
                            'slug' => (string) $topic->slug,
                            'title' => (string) ($topic->title ?: $topic->keyword),
                            'keyword' => (string) $topic->keyword,
                        ];
                    })->values()->all();

                $childCategories = $root->children->map(function (Category $child) {
                    return [
                        'id' => (int) $child->id,
                        'name' => (string) $child->name,
                        'slug' => (string) $child->slug,
                    ];
                })->values()->all();

                return [
                    'id' => (int) $root->id,
                    'name' => (string) $root->name,
                    'slug' => (string) $root->slug,
                    'is_public_figure' => $isPublicFigure,
                    'top_entities' => $entities,
                    'child_categories' => $childCategories,
                    'topics' => $categoryTopics,
                    'category_url' => route('categories.show', $root->slug),
                    'top_ranking_url' => route('rankings.show', $root->slug),
                ];
            })->values()->all();
        });
    }

    /**
     * Get up to 12 popular published and indexable topics.
     *
     * @return array<int, array{
     *     id: int,
     *     slug: string,
     *     title: string,
     *     keyword: string
     * }>
     */
    public function getPopularTopics(): array
    {
        return Cache::remember(self::CACHE_KEY_POPULAR_TOPICS, self::CACHE_TTL_SECONDS, function () {
            $publishedTopics = SearchLandingPage::query()
                ->published()
                ->with(['category.parent', 'themes'])
                ->orderByDesc('candidate_signal')
                ->orderBy('title')
                ->get();

            $indexableTopics = $publishedTopics->filter(fn (SearchLandingPage $t) => $this->topicEntityList->isIndexable($t));

            return $indexableTopics
                ->take(12)
                ->map(fn ($topic) => [
                    'id' => (int) $topic->id,
                    'slug' => (string) $topic->slug,
                    'title' => (string) ($topic->title ?: $topic->keyword),
                    'keyword' => (string) $topic->keyword,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Invalidate homepage category blocks and popular topics caches.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_BLOCKS);
        Cache::forget(self::CACHE_KEY_POPULAR_TOPICS);
    }
}
