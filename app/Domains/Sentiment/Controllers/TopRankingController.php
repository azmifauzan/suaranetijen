<?php

namespace App\Domains\Sentiment\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Services\CategoryContext;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\RobotsPolicy;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Services\SentimentRankingService;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\MergeProp;
use Inertia\Response;

class TopRankingController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        protected SentimentRankingService $rankingService,
        protected SponsorLeaderboardService $sponsorLeaderboardService
    ) {}

    /**
     * Global ranking across all categories.
     */
    public function index(Request $request): Response
    {
        $periodParam = $request->query('period', '365d');
        $period = Period::tryFrom((string) $periodParam) ?? Period::OneYear;

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $sponsorTeaser = $this->sponsorLeaderboardService->getHomepageTeaser(3);

        return Inertia::render('Top/Index', [
            'period' => $period->value,
            'categories' => $categories,
            'sponsorTeaser' => $sponsorTeaser,
            ...$this->rankingPage($request, null, $period, withCategory: true)[0],
        ]);
    }

    /**
     * Display category ranking by Sentimen Netijen.
     */
    public function show(string $slug, Request $request): Response
    {
        /** @var Category $category */
        $category = Category::query()
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        $periodParam = $request->query('period', '365d');
        $period = Period::tryFrom((string) $periodParam) ?? Period::OneYear;

        [$rankingProps, $rankingIsEmpty] = $this->rankingPage($request, $category->id, $period, withCategory: false);

        // An empty ranking is a stub page ("Belum Ada Ranking"); keep it out of the index (docs/31).
        if ($rankingIsEmpty) {
            RobotsPolicy::noindex();
        }

        $otherCategories = Category::query()
            ->active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $sponsorTeaser = $this->sponsorLeaderboardService->getCategoryTeaser($category->id, 3);

        $relatedTopics = SearchLandingPage::query()
            ->published()
            ->where('category_id', $category->id)
            ->latest('published_at')
            ->limit(6)
            ->get(['id', 'slug', 'keyword', 'title'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->title ?: $t->keyword,
                'keyword' => $t->keyword,
            ])
            ->values();

        $contextDescription = CategoryContext::getDescription($category->slug, $category->name);

        return Inertia::render('Top/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'context_description' => $contextDescription,
            ],
            'period' => $period->value,
            'otherCategories' => $otherCategories,
            'sponsorTeaser' => $sponsorTeaser,
            'relatedTopics' => $relatedTopics,
            ...$rankingProps,
        ]);
    }

    /**
     * One page of the ranking. "Muat lebih banyak" requests the next page as a partial reload, and the merge prop
     * appends it to the rows already on screen.
     *
     * @return array{0: array{rankings: MergeProp, nextPage: int|null}, 1: bool} the page props, and whether the page is empty
     */
    private function rankingPage(Request $request, ?int $categoryId, Period $period, bool $withCategory): array
    {
        $page = max(1, $request->integer('page', 1));

        // One extra row tells whether a next page exists without a separate count query.
        $rows = $this->rankingService->getRanking($categoryId, $period, limit: self::PER_PAGE + 1, offset: ($page - 1) * self::PER_PAGE);
        $hasMore = $rows->count() > self::PER_PAGE;

        $rankings = $rows->take(self::PER_PAGE)->map(function (array $item) use ($withCategory): array {
            $pos = $item['positive_count'];
            $neu = $item['neutral_count'];
            $neg = $item['negative_count'];
            $total = max(1, $pos + $neu + $neg);

            return [
                'rank' => $item['rank'],
                'entity' => [
                    'id' => $item['entity']->id,
                    'name' => $item['entity']->name,
                    'slug' => $item['entity']->slug,
                    'type' => $item['entity']->type->value,
                    'type_label' => $item['entity']->type->label(),
                ],
                ...($withCategory ? ['category' => [
                    'name' => $item['entity']->category->name,
                    'slug' => $item['entity']->category->slug,
                ]] : []),
                'score' => $item['score'],
                'opinion_count' => $item['opinion_count'],
                'distribution' => [
                    'positive' => $pos,
                    'neutral' => $neu,
                    'negative' => $neg,
                    'positive_pct' => round(($pos / $total) * 100, 1),
                    'neutral_pct' => round(($neu / $total) * 100, 1),
                    'negative_pct' => round(($neg / $total) * 100, 1),
                ],
            ];
        })->values();

        return [
            ['rankings' => Inertia::merge($rankings)->matchOn('entity.id'), 'nextPage' => $hasMore ? $page + 1 : null],
            $rankings->isEmpty(),
        ];
    }
}
