<?php

use App\Domains\Entities\Controllers\CategoryShowController;
use App\Domains\Entities\Controllers\EntityShowController;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Ratings\Controllers\Api\RatingController;
use App\Domains\Search\Controllers\Api\SearchController;
use App\Domains\Search\Controllers\SearchPageController;
use App\Domains\Search\Services\SearchSuggestionService;
use App\Domains\Sentiment\Controllers\Api\CategoryRankingController;
use App\Domains\Sentiment\Controllers\TopRankingController;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Domains\Sponsorships\Controllers\Api\SponsorLeaderboardController;
use App\Domains\Sponsorships\Controllers\Api\SponsorshipOrderController;
use App\Domains\Sponsorships\Controllers\Api\SumopodRelayWebhookController;
use App\Domains\Sponsorships\Controllers\SponsorBoardPageController;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (SearchSuggestionService $searchSuggestionService, SponsorLeaderboardService $sponsorLeaderboardService) {
    $minOpinions = (int) config('scoring.public_min_opinions', 30);

    $topEntities = SentimentSnapshot::query()
        ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
        ->where('entities.status', EntityStatus::Active)
        ->where('entities.searchable', true)
        ->where('sentiment_snapshots.period', Period::OneYear->value)
        ->where('sentiment_snapshots.opinion_count', '>=', $minOpinions)
        ->where('sentiment_snapshots.score', '>=', 70)
        ->orderByDesc('sentiment_snapshots.score')
        ->orderByDesc('sentiment_snapshots.opinion_count')
        ->select('sentiment_snapshots.*')
        ->with('entity.category')
        ->limit(6)
        ->get()
        ->map(fn (SentimentSnapshot $snap) => [
            'id' => $snap->entity->id,
            'name' => $snap->entity->name,
            'slug' => $snap->entity->slug,
            'type_label' => $snap->entity->type->label(),
            'category_name' => $snap->entity->category->name,
            'score' => (float) $snap->score,
            'opinion_count' => (int) $snap->opinion_count,
        ]);

    $recentEntities = SentimentSnapshot::query()
        ->join('entities', 'entities.id', '=', 'sentiment_snapshots.entity_id')
        ->where('entities.status', EntityStatus::Active)
        ->where('entities.searchable', true)
        ->where('sentiment_snapshots.period', Period::OneYear->value)
        ->where('sentiment_snapshots.opinion_count', '>', 0)
        ->orderByDesc('sentiment_snapshots.updated_at')
        ->select('sentiment_snapshots.*')
        ->with('entity.category')
        ->limit(6)
        ->get()
        ->map(fn (SentimentSnapshot $snap) => [
            'id' => $snap->entity->id,
            'name' => $snap->entity->name,
            'slug' => $snap->entity->slug,
            'type_label' => $snap->entity->type->label(),
            'category_name' => $snap->entity->category->name,
            'score' => ScoreCalculator::isPublicScoreEligible((int) $snap->opinion_count) ? (float) $snap->score : null,
            'opinion_count' => (int) $snap->opinion_count,
            'updated_at' => $snap->updated_at?->diffForHumans(),
        ]);

    return Inertia::render('Welcome', [
        'categories' => Category::active()
            ->whereDoesntHave('children')
            ->withCount('entities')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']),
        'searchSuggestions' => $searchSuggestionService->getSuggestions(),
        'topEntities' => $topEntities,
        'recentEntities' => $recentEntities,
        'sponsorTeaser' => $sponsorLeaderboardService->getHomepageTeaser(3),
    ]);
})->name('home');

Route::get('/sponsor', [SponsorBoardPageController::class, 'index'])->name('sponsor.index');
Route::get('/api/sponsor/leaderboard', [SponsorLeaderboardController::class, 'index'])->name('api.sponsor.leaderboard');
Route::post('/api/sponsor/webhooks/sumopod-relay', [SumopodRelayWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.sponsor.webhooks.relay');

Route::get('/search', [SearchPageController::class, 'index'])->name('search.index');
Route::get('/api/search', [SearchController::class, 'index'])->name('api.search');

Route::get('/e/{slug}', [EntityShowController::class, 'show'])->name('entities.show');
Route::get('/category/{slug}', [CategoryShowController::class, 'show'])->name('categories.show');
Route::get('/top/{slug}', [TopRankingController::class, 'show'])->name('rankings.show');
Route::get('/api/categories/{slug}/ranking', [CategoryRankingController::class, 'index'])->name('api.categories.ranking');

// Static and trust pages (docs/04, docs/17)
Route::get('/methodology', [StaticPageController::class, 'methodology'])->name('methodology');
Route::get('/sources', [StaticPageController::class, 'sources'])->name('sources');
Route::get('/about', [StaticPageController::class, 'about'])->name('about');
Route::get('/terms', [StaticPageController::class, 'terms'])->name('terms');
Route::get('/privacy', [StaticPageController::class, 'privacy'])->name('privacy');

// SEO XML Sitemap (docs/13, docs/17)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::middleware(['auth', 'throttle:ratings'])->group(function (): void {
    Route::put('/api/entities/{entity}/rating', [RatingController::class, 'update'])->name('api.entities.rating.update');
    Route::delete('/api/entities/{entity}/rating', [RatingController::class, 'destroy'])->name('api.entities.rating.destroy');
});

Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
    Route::post('/api/sponsor/orders', [SponsorshipOrderController::class, 'store'])->name('api.sponsor.orders.store');
    Route::get('/api/sponsor/orders/{order}', [SponsorshipOrderController::class, 'show'])->name('api.sponsor.orders.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
