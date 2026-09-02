<?php

namespace App\Domains\Sentiment\Controllers\Api;

use App\Domains\Entities\Models\Category;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Services\SentimentRankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryRankingController extends Controller
{
    public function __construct(
        protected SentimentRankingService $rankingService
    ) {}

    /**
     * Return JSON ranking for a category.
     */
    public function index(string $slug, Request $request): JsonResponse
    {
        /** @var Category $category */
        $category = Category::query()
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        $periodParam = $request->query('period', '365d');
        $period = Period::tryFrom((string) $periodParam) ?? Period::OneYear;

        $rankings = $this->rankingService->getRanking($category->id, $period);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'period' => $period->value,
            'total' => $rankings->count(),
            'data' => $rankings->map(function ($item) {
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
                    'positive_count' => $pos,
                    'neutral_count' => $neu,
                    'negative_count' => $neg,
                    'positive_pct' => round(($pos / $total) * 100, 1),
                    'neutral_pct' => round(($neu / $total) * 100, 1),
                    'negative_pct' => round(($neg / $total) * 100, 1),
                ];
            }),
        ]);
    }
}
