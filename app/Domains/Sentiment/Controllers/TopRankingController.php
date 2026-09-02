<?php

namespace App\Domains\Sentiment\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Services\SentimentRankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopRankingController extends Controller
{
    public function __construct(
        protected SentimentRankingService $rankingService
    ) {}

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

        $rankings = $this->rankingService->getRanking($category->id, $period);

        $otherCategories = Category::query()
            ->active()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Top/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'period' => $period->value,
            'otherCategories' => $otherCategories,
            'rankings' => $rankings->map(function ($item) {
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
            }),
        ]);
    }
}
