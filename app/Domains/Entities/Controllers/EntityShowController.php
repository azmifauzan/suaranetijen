<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntityOgImage;
use App\Domains\Entities\Services\EntitySeoService;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\RobotsPolicy;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Themes\Services\TopThemesService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntityShowController extends Controller
{
    public function __construct(
        protected TopThemesService $topThemesService,
        protected EntitySeoService $entitySeoService,
        protected EntityOgImage $ogImage
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

        if (! $isPublicScoreEligible) {
            RobotsPolicy::noindex();
        }

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

        $seoData = $this->entitySeoService->generate(
            $entity,
            $sentimentData,
            $themesData,
            [
                'rating_count' => $entity->ratingSnapshot ? (int) $entity->ratingSnapshot->rating_count : 0,
                'rating_average' => $entity->ratingSnapshot?->rating_average !== null ? (float) $entity->ratingSnapshot->rating_average : null,
            ]
        );

        $relatedEntities = $this->buildRelatedEntities($entity);

        // Leaderboard active status & views tracking
        $activeSponsoredEntry = SponsoredEntry::query()
            ->active()
            ->where('entity_id', $entity->id)
            ->whereHas('period', fn ($q) => $q->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->first();

        if ($activeSponsoredEntry) {
            $activeSponsoredEntry->increment('views_count');
        }

        $currentUserRating = $request->user()
            ? UserRating::query()
                ->whereBelongsTo($entity)
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->first()
            : null;

        $userReviews = UserRating::query()
            ->whereBelongsTo($entity)
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (UserRating $r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'review' => $r->review,
                'user_name' => $r->user->name,
                'created_at' => $r->created_at?->diffForHumans() ?? '',
            ]);

        // Daily sentiment trend (up to 30 days)
        $trend = SentimentDaily::query()
            ->where('entity_id', $entity->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (SentimentDaily $d) => [
                'date' => $d->date->format('Y-m-d'),
                'label' => $d->date->format('d M'),
                'score' => $d->score !== null ? (float) $d->score : null,
                'opinion_count' => (int) $d->opinion_count,
                'positive_count' => (int) $d->positive_count,
                'neutral_count' => (int) $d->neutral_count,
                'negative_count' => (int) $d->negative_count,
            ]);

        return Inertia::render('Entities/Show', [
            'trend' => $trend,
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'type' => $entity->type->value,
                'type_label' => $entity->type->label(),
                'description' => $entity->description,
                'website_url' => $entity->website_url,
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
                'user_rating' => $currentUserRating ? (int) $currentUserRating->rating : null,
                'user_review' => $currentUserRating?->review,
            ],
            'userReviews' => $userReviews,
            'leaderboard' => $activeSponsoredEntry ? [
                'is_active' => true,
                'clicks_count' => (int) $activeSponsoredEntry->clicks_count,
                'views_count' => (int) $activeSponsoredEntry->views_count,
                'direct_url' => route('leaderboard.redirect', ['slug' => $entity->slug]),
                'website_url' => $entity->website_url,
            ] : null,
            'themes' => $themesData,
            'relatedEntities' => $relatedEntities->map(fn (Entity $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'slug' => $e->slug,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
            ]),
            'includedTopics' => SearchLandingPage::query()
                ->published()
                ->where(function ($q) use ($entity) {
                    $q->where('category_id', $entity->category_id);
                    if ($entity->category->parent_id) {
                        $q->orWhere('category_id', $entity->category->parent_id);
                    }
                })
                ->whereHas('themes', function ($tq) use ($entity) {
                    $tq->whereHas('snapshots', function ($sq) use ($entity) {
                        $sq->where('entity_id', $entity->id)
                            ->where('observation_count', '>', 0);
                    });
                })
                ->latest('published_at')
                ->limit(4)
                ->get(['id', 'slug', 'keyword', 'title'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'title' => $t->title ?: $t->keyword,
                    'keyword' => $t->keyword,
                ])
                ->values(),
            'entitySeo' => $seoData,
            'ogImage' => $this->ogImageUrl($entity),
        ])->withViewData('robots', RobotsPolicy::get());
    }

    /**
     * Related entities, prioritized by actual brand-family relation over raw
     * category membership — parent brand and sibling products/services first
     * (a fixed alphabetical/category-only pick showed near-identical results
     * for every entity in a small category), falling back to a random pick
     * from the same category only to fill remaining slots.
     *
     * @return Collection<int, Entity>
     */
    private function buildRelatedEntities(Entity $entity): Collection
    {
        $familyQuery = Entity::query()->active()->where('id', '!=', $entity->id);

        if ($entity->parent_id !== null) {
            $familyQuery->where(fn ($q) => $q->where('parent_id', $entity->parent_id)->orWhere('id', $entity->parent_id));
        } else {
            $familyQuery->where('parent_id', $entity->id);
        }

        $related = $familyQuery->inRandomOrder()->limit(4)->get(['id', 'name', 'slug', 'type']);

        if ($related->count() < 4) {
            $exclude = $related->pluck('id')->push($entity->id);

            $fallback = Entity::query()
                ->where('category_id', $entity->category_id)
                ->whereNotIn('id', $exclude)
                ->active()
                ->inRandomOrder()
                ->limit(4 - $related->count())
                ->get(['id', 'name', 'slug', 'type']);

            $related = $related->merge($fallback);
        }

        return $related;
    }

    /**
     * Absolute URL of the entity's share card, or null when it does not clear the public threshold
     * (the page then keeps the site-wide image). The version query rolls the URL when a number changes.
     */
    protected function ogImageUrl(Entity $entity): ?string
    {
        $snapshot = $this->ogImage->eligibleSnapshot($entity);

        if ($snapshot === null) {
            return null;
        }

        return route('og.entity', ['slug' => $entity->slug, 'v' => $this->ogImage->version($snapshot)]);
    }
}
