<?php

namespace App\Domains\Entities\Controllers;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Sentiment\Services\SentimentRankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryShowController extends Controller
{
    public function __construct(
        protected SentimentRankingService $rankingService
    ) {}

    /**
     * Display the specified category overview page (docs/04).
     */
    public function show(string $slug, Request $request): Response
    {
        /** @var Category $category */
        $category = Category::query()
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        $searchQuery = $request->query('q');

        // A root category has no entities of its own (Automotive, Technology, ...): include its children.
        $categoryIds = Category::query()
            ->where('id', $category->id)
            ->orWhere('parent_id', $category->id)
            ->pluck('id');

        // Top Sentimen entities in this category (meeting public threshold)
        $topRankings = $this->rankingService->getRanking(
            categoryId: $category->id,
            period: Period::OneYear,
            minOpinions: (int) config('scoring.public_min_opinions', 30),
            limit: 6
        );

        if ($topRankings->isEmpty()) {
            $topRankings = $this->rankingService->getRanking(
                categoryId: $category->id,
                period: Period::All,
                minOpinions: (int) config('scoring.public_min_opinions', 30),
                limit: 6
            );
        }

        // Most Discussed entities
        $mostDiscussed = SentimentSnapshot::query()
            ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
            ->whereIn('entities.category_id', $categoryIds)
            ->where('entities.status', EntityStatus::Active)
            ->where('sentiment_snapshots.period', Period::OneYear->value)
            ->where('sentiment_snapshots.opinion_count', '>', 0)
            ->orderByDesc('sentiment_snapshots.opinion_count')
            ->select('sentiment_snapshots.*')
            ->with('entity')
            ->limit(6)
            ->get()
            ->map(fn (SentimentSnapshot $snap) => [
                'id' => $snap->entity->id,
                'name' => $snap->entity->name,
                'slug' => $snap->entity->slug,
                'type' => $snap->entity->type->value,
                'type_label' => $snap->entity->type->label(),
                'opinion_count' => (int) $snap->opinion_count,
                'score' => ScoreCalculator::isPublicScoreEligible((int) $snap->opinion_count) ? (float) $snap->score : null,
            ]);

        // Recently Updated entities
        $recentlyUpdated = SentimentSnapshot::query()
            ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
            ->whereIn('entities.category_id', $categoryIds)
            ->where('entities.status', EntityStatus::Active)
            ->where('sentiment_snapshots.period', Period::OneYear->value)
            ->orderByDesc('sentiment_snapshots.updated_at')
            ->select('sentiment_snapshots.*')
            ->with('entity')
            ->limit(6)
            ->get()
            ->map(fn (SentimentSnapshot $snap) => [
                'id' => $snap->entity->id,
                'name' => $snap->entity->name,
                'slug' => $snap->entity->slug,
                'type' => $snap->entity->type->value,
                'type_label' => $snap->entity->type->label(),
                'opinion_count' => (int) $snap->opinion_count,
                'score' => ScoreCalculator::isPublicScoreEligible((int) $snap->opinion_count) ? (float) $snap->score : null,
                'updated_at' => $snap->updated_at?->diffForHumans(),
            ]);

        // Filtered entities if search query provided
        $filteredEntities = null;
        if (is_string($searchQuery) && trim($searchQuery) !== '') {
            $filteredEntities = Entity::query()
                ->whereIn('category_id', $categoryIds)
                ->active()
                ->where('name', 'ilike', '%'.trim($searchQuery).'%')
                ->limit(20)
                ->get(['id', 'name', 'slug', 'type', 'description']);
        }

        $otherCategories = Category::query()
            ->active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $totalEntities = Entity::query()
            ->whereIn('category_id', $categoryIds)
            ->active()
            ->count();

        return Inertia::render('Category/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'total_entities' => $totalEntities,
            ],
            'topSentimen' => $topRankings->map(fn ($item) => [
                'rank' => $item['rank'],
                'id' => $item['entity']->id,
                'name' => $item['entity']->name,
                'slug' => $item['entity']->slug,
                'type' => $item['entity']->type->value,
                'type_label' => $item['entity']->type->label(),
                'score' => $item['score'],
                'opinion_count' => $item['opinion_count'],
            ]),
            'mostDiscussed' => $mostDiscussed,
            'recentlyUpdated' => $recentlyUpdated,
            'filteredEntities' => $filteredEntities,
            'otherCategories' => $otherCategories,
            'searchQuery' => $searchQuery,
            'relatedTopics' => SearchLandingPage::query()
                ->published()
                ->whereIn('category_id', $categoryIds)
                ->latest('published_at')
                ->limit(6)
                ->get(['id', 'slug', 'keyword', 'title'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'title' => $t->title ?: $t->keyword,
                    'keyword' => $t->keyword,
                ])
                ->values(),
        ]);
    }
}
