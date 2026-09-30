<?php

use App\Domains\Entities\Controllers\CategoryShowController;
use App\Domains\Entities\Controllers\EntityShowController;
use App\Domains\Ratings\Controllers\Api\RatingController;
use App\Domains\Search\Controllers\Api\SearchController;
use App\Domains\Search\Controllers\SearchPageController;
use App\Domains\Search\Controllers\TopicIndexController;
use App\Domains\Search\Controllers\TopicShowController;
use App\Domains\Sentiment\Controllers\Api\CategoryRankingController;
use App\Domains\Sentiment\Controllers\TopRankingController;
use App\Domains\Sponsorships\Controllers\Api\SponsorLeaderboardController;
use App\Domains\Sponsorships\Controllers\Api\SponsorshipOrderController;
use App\Domains\Sponsorships\Controllers\Api\SponsorUrlPreviewController;
use App\Domains\Sponsorships\Controllers\Api\SumopodRelayWebhookController;
use App\Domains\Sponsorships\Controllers\SponsorBoardPageController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePageController::class)->name('home');

Route::get('/leaderboard', [SponsorBoardPageController::class, 'index'])->name('leaderboard.index');
Route::get('/sponsor', fn () => redirect()->route('leaderboard.index', [], 301))->name('sponsor.index');
Route::middleware('auth')->get('/sponsor/payment-status', [SponsorBoardPageController::class, 'paymentStatus'])
    ->name('sponsor.payment-status');
Route::get('/go/{slug}', [SponsorBoardPageController::class, 'redirect'])->name('leaderboard.redirect');
Route::match(['get', 'post'], '/api/sponsor/click/{slug}', [SponsorBoardPageController::class, 'trackClick'])
    ->middleware('throttle:60,1')
    ->name('api.sponsor.click');
Route::get('/api/sponsor/leaderboard', [SponsorLeaderboardController::class, 'index'])->name('api.sponsor.leaderboard');
Route::post('/api/sponsor/preview', SponsorUrlPreviewController::class)
    ->middleware('throttle:20,1')
    ->name('api.sponsor.preview');
Route::post('/api/sponsor/webhooks/sumopod-relay', [SumopodRelayWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.sponsor.webhooks.relay');

Route::get('/search', [SearchPageController::class, 'index'])->name('search.index');
Route::get('/api/search', [SearchController::class, 'index'])->name('api.search');

Route::get('/e/{slug}', [EntityShowController::class, 'show'])->name('entities.show');
Route::get('/category/{slug}', [CategoryShowController::class, 'show'])->name('categories.show');
Route::get('/top', [TopRankingController::class, 'index'])->name('rankings.index');
Route::get('/top/{slug}', [TopRankingController::class, 'show'])->name('rankings.show');
Route::get('/api/categories/{slug}/ranking', [CategoryRankingController::class, 'index'])->name('api.categories.ranking');

// Topic Landing Pages (docs/28)
Route::get('/topik', [TopicIndexController::class, 'index'])->name('topics.index');
Route::get('/topik/{slug}', [TopicShowController::class, 'show'])->name('topics.show');

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

// No `auth` middleware: sponsoring doesn't require an account (docs/26) — a guest's email
// resolves/creates a real User inside the controller instead.
Route::middleware('throttle:30,1')->post('/api/sponsor/orders', [SponsorshipOrderController::class, 'store'])->name('api.sponsor.orders.store');

Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
    Route::get('/api/sponsor/orders/{order}', [SponsorshipOrderController::class, 'show'])->name('api.sponsor.orders.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
