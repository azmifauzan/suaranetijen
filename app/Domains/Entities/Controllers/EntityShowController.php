<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Themes\Services\TopThemesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntityShowController extends Controller
{
    public function __construct(
        protected TopThemesService $topThemesService
    ) {}

    /**
     * Display the specified entity public page.
     */
    public function show(string $slug, Request $request): Response
    {
        /** @var Entity $entity */
        $entity = Entity::query()
            ->with(['category', 'parent', 'aliases', 'ratingSnapshot'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        // Period resolution: query param or 365d default, fallback to 'all' if 365d empty
        $requestedPeriodStr = $request->query('period');
        $snapshots = SentimentSnapshot::query()
            ->where('entity_id', $entity->id)
            ->get()
            ->keyBy(fn (SentimentSnapshot $s) => $s->period->value);

        $selectedPeriod = Period::OneYear;
        if ($requestedPeriodStr && ($p = Period::tryFrom($requestedPeriodStr))) {
            $selectedPeriod = $p;
        } elseif (! $snapshots->has(Period::OneYear->value) && $snapshots->has(Period::All->value)) {
            $selectedPeriod = Period::All;
        }

        $activeSnapshot = $snapshots->get($selectedPeriod->value) ?? $snapshots->get(Period::All->value);

        $sentimentData = null;
        $opinionCount = $activeSnapshot ? (int) $activeSnapshot->opinion_count : 0;
        $isPublicScoreEligible = ScoreCalculator::isPublicScoreEligible($opinionCount);

        if ($activeSnapshot && $isPublicScoreEligible && $activeSnapshot->score !== null) {
            $pos = (int) $activeSnapshot->positive_count;
            $neu = (int) $activeSnapshot->neutral_count;
            $neg = (int) $activeSnapshot->negative_count;
            $total = max(1, $pos + $neu + $neg);

            $sentimentData = [
                'is_eligible' => true,
                'score' => (float) $activeSnapshot->score,
                'opinion_count' => $opinionCount,
                'positive_count' => $pos,
                'neutral_count' => $neu,
                'negative_count' => $neg,
                'distribution' => [
                    'positive_pct' => round(($pos / $total) * 100, 1),
                    'neutral_pct' => round(($neu / $total) * 100, 1),
                    'negative_pct' => round(($neg / $total) * 100, 1),
                ],
                'model_version' => $activeSnapshot->sentiment_model_version,
                'formula_version' => $activeSnapshot->score_formula_version,
            ];
        } else {
            $sentimentData = [
                'is_eligible' => false,
                'score' => null,
                'opinion_count' => $opinionCount,
                'positive_count' => $activeSnapshot ? (int) $activeSnapshot->positive_count : 0,
                'neutral_count' => $activeSnapshot ? (int) $activeSnapshot->neutral_count : 0,
                'negative_count' => $activeSnapshot ? (int) $activeSnapshot->negative_count : 0,
                'distribution' => null,
                'empty_state_message' => 'Crawler opini publik belum mengumpulkan minimal 30 opini netijen untuk entitas ini. Skor agregat publik akan dihitung otomatis saat pipeline observasi aktif.',
            ];
        }

        // Top Suara Netijen (Theme Index per docs/25)
        $themesData = $this->topThemesService->getTopThemesForEntity($entity, $selectedPeriod);

        // Related entities in the same category
        $relatedEntities = Entity::query()
            ->where('category_id', $entity->category_id)
            ->where('id', '!=', $entity->id)
            ->active()
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'slug', 'type']);

        $userRating = $request->user()
            ? UserRating::query()
                ->whereBelongsTo($entity)
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->value('rating')
            : null;

        return Inertia::render('Entities/Show', [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'type' => $entity->type->value,
                'type_label' => $entity->type->label(),
                'description' => $entity->description,
                'searchable' => $entity->searchable,
                'rankable' => $entity->rankable,
                'category' => [
                    'id' => $entity->category->id,
                    'name' => $entity->category->name,
                    'slug' => $entity->category->slug,
                ],
                'parent' => $entity->parent ? [
                    'id' => $entity->parent->id,
                    'name' => $entity->parent->name,
                    'slug' => $entity->parent->slug,
                ] : null,
                'aliases' => $entity->aliases->map(fn ($alias) => [
                    'id' => $alias->id,
                    'alias' => $alias->alias,
                    'alias_type' => $alias->alias_type->value,
                ]),
            ],
            'period' => $selectedPeriod->value,
            'availablePeriods' => array_map(fn (Period $p) => $p->value, Period::cases()),
            'sentiment' => $sentimentData,
            'rating' => [
                'rating_count' => $entity->ratingSnapshot
                    ? (int) $entity->ratingSnapshot->rating_count
                    : 0,
                'rating_average' => $entity->ratingSnapshot?->rating_average === null
                    ? null
                    : (float) $entity->ratingSnapshot->rating_average,
                'user_rating' => $userRating === null ? null : (int) $userRating,
            ],
            'themes' => $themesData,
            'relatedEntities' => $relatedEntities->map(fn (Entity $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'slug' => $e->slug,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
            ]),
        ]);
    }
}
