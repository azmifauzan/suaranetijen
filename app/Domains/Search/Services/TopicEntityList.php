<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Cache;

class TopicEntityList
{
    /**
     * Get entity list and indexability for a topic landing page.
     *
     * @return array{
     *     entities: list<array{
     *         id: int,
     *         name: string,
     *         slug: string,
     *         type_label: string,
     *         mention_count: int,
     *         score: float|null,
     *         quote: string|null,
     *         theme_breakdown: list<array{theme_id: int, display_label: string, mention_count: int}>
     *     }>,
     *     is_indexable: bool,
     *     qualifying_count: int,
     *     window: string
     * }
     */
    public function get(SearchLandingPage $topic, bool $useCache = true): array
    {
        if (! $useCache) {
            return $this->build($topic);
        }

        $timestamp = $topic->updated_at->timestamp;
        $cacheKey = "topic:entities:{$topic->id}:{$timestamp}";

        return Cache::remember($cacheKey, 3600, fn () => $this->build($topic));
    }

    public function isIndexable(SearchLandingPage $topic): bool
    {
        $result = $this->get($topic);

        return $result['is_indexable'];
    }

    /**
     * @return array{
     *     entities: list<array{
     *         id: int,
     *         name: string,
     *         slug: string,
     *         type_label: string,
     *         mention_count: int,
     *         score: float|null,
     *         quote: string|null,
     *         theme_breakdown: list<array{theme_id: int, display_label: string, mention_count: int}>
     *     }>,
     *     is_indexable: bool,
     *     qualifying_count: int,
     *     window: string
     * }
     */
    public function build(SearchLandingPage $topic): array
    {
        $topic->loadMissing(['category.parent', 'themes']);

        if ($topic->category_id === null || $topic->themes->isEmpty()) {
            return [
                'entities' => [],
                'is_indexable' => false,
                'qualifying_count' => 0,
                'window' => Period::OneYear->value,
            ];
        }

        if ($topic->category && $topic->category->isPublicFigureCategory()) {
            return [
                'entities' => [],
                'is_indexable' => false,
                'qualifying_count' => 0,
                'window' => Period::OneYear->value,
            ];
        }

        $categoryIds = Category::query()
            ->where('id', $topic->category_id)
            ->orWhere('parent_id', $topic->category_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $themeIds = $topic->themes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $minEntities = (int) config('landing_pages.index_min_entities', 3);
        $minMentions = (int) config('landing_pages.index_min_mentions_per_entity', 3);

        // 1. Try 365d window first
        $entities365 = $this->queryEntitiesForWindow($categoryIds, $themeIds, $topic, Period::OneYear);
        $qualifying365 = count(array_filter($entities365, fn (array $e) => $e['mention_count'] >= $minMentions));

        if ($qualifying365 >= $minEntities) {
            return [
                'entities' => array_slice($entities365, 0, 20),
                'is_indexable' => true,
                'qualifying_count' => $qualifying365,
                'window' => Period::OneYear->value,
            ];
        }

        // 2. Fallback to All window if 365d is below index threshold
        $entitiesAll = $this->queryEntitiesForWindow($categoryIds, $themeIds, $topic, Period::All);
        $qualifyingAll = count(array_filter($entitiesAll, fn (array $e) => $e['mention_count'] >= $minMentions));

        $chosenEntities = ! empty($entitiesAll) ? $entitiesAll : $entities365;
        $chosenWindow = ! empty($entitiesAll) ? Period::All->value : Period::OneYear->value;
        $qualifyingCount = ! empty($entitiesAll) ? $qualifyingAll : $qualifying365;
        $isIndexable = $qualifyingAll >= $minEntities;

        return [
            'entities' => array_slice($chosenEntities, 0, 20),
            'is_indexable' => $isIndexable,
            'qualifying_count' => $qualifyingCount,
            'window' => $chosenWindow,
        ];
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $themeIds
     * @return list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     type_label: string,
     *     mention_count: int,
     *     score: float|null,
     *     quote: string|null,
     *     theme_breakdown: list<array{theme_id: int, display_label: string, mention_count: int}>
     * }>
     */
    private function queryEntitiesForWindow(
        array $categoryIds,
        array $themeIds,
        SearchLandingPage $topic,
        Period $period
    ): array {
        $themesById = $topic->themes->keyBy('id');

        // Fetch theme snapshots for active, searchable entities in matching categories
        $snapshots = EntityThemeSnapshot::query()
            ->whereIn('theme_id', $themeIds)
            ->where('window', $period->value)
            ->whereHas('entity', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds)
                    ->where('status', EntityStatus::Active)
                    ->where('searchable', true);
            })
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        $snapshotsByEntity = $snapshots->groupBy('entity_id');
        $entityIds = $snapshotsByEntity->keys()->all();

        $entities = Entity::query()
            ->whereIn('id', $entityIds)
            ->where('status', EntityStatus::Active)
            ->where('searchable', true)
            ->get()
            ->keyBy('id');

        // Fetch sentiment scores for the entities
        $sentimentSnapshots = SentimentSnapshot::query()
            ->whereIn('entity_id', $entityIds)
            ->whereIn('period', [$period->value, Period::All->value])
            ->get()
            ->groupBy('entity_id');

        // Fetch sample quotes per entity for topic themes
        $quotes = ThemeObservation::query()
            ->whereIn('entity_id', $entityIds)
            ->whereIn('theme_id', $themeIds)
            ->whereNotNull('context')
            ->where('context', '!=', '')
            ->orderByDesc('id')
            ->get(['entity_id', 'context'])
            ->unique('entity_id')
            ->pluck('context', 'entity_id');

        $result = [];

        foreach ($snapshotsByEntity as $entityId => $entitySnapshots) {
            $entity = $entities->get($entityId);
            if (! $entity) {
                continue;
            }

            $mentionCount = 0;
            $themeBreakdown = [];

            foreach ($entitySnapshots as $snap) {
                $count = (int) $snap->observation_count;
                $mentionCount += $count;

                $theme = $themesById->get($snap->theme_id);
                if ($theme && $count > 0) {
                    $themeBreakdown[] = [
                        'theme_id' => $theme->id,
                        'display_label' => $theme->display_label,
                        'mention_count' => $count,
                    ];
                }
            }

            if ($mentionCount <= 0) {
                continue;
            }

            // Determine sentiment score (preferred: current window, fallback: all)
            $entitySentiments = $sentimentSnapshots->get($entityId);
            $sentimentSnap = $entitySentiments?->firstWhere('period', $period)
                ?? $entitySentiments?->firstWhere('period', Period::All);

            $score = null;
            if ($sentimentSnap && ScoreCalculator::isPublicScoreEligible((int) $sentimentSnap->opinion_count)) {
                $score = $sentimentSnap->score !== null ? (float) $sentimentSnap->score : null;
            }

            $rawQuote = $quotes->get($entityId);
            $quote = $rawQuote ? mb_substr(trim($rawQuote), 0, 200) : null;

            $result[] = [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'type_label' => $entity->type->label(),
                'mention_count' => $mentionCount,
                'score' => $score,
                'quote' => $quote,
                'theme_breakdown' => $themeBreakdown,
            ];
        }

        // Sort: mention_count desc, score desc NULLS LAST, name asc
        usort($result, function (array $a, array $b): int {
            if ($a['mention_count'] !== $b['mention_count']) {
                return $b['mention_count'] <=> $a['mention_count'];
            }

            if ($a['score'] !== $b['score']) {
                if ($a['score'] === null) {
                    return 1;
                }
                if ($b['score'] === null) {
                    return -1;
                }

                return $b['score'] <=> $a['score'];
            }

            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }
}
