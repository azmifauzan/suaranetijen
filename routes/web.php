<?php

use App\Domains\Entities\Controllers\EntityShowController;
use App\Domains\Entities\Models\Category;
use App\Domains\Ratings\Controllers\Api\RatingController;
use App\Domains\Search\Controllers\Api\SearchController;
use App\Domains\Search\Controllers\SearchPageController;
use App\Domains\Sentiment\Controllers\Api\CategoryRankingController;
use App\Domains\Sentiment\Controllers\TopRankingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'slug']),
    ]);
})->name('home');

Route::get('/search', [SearchPageController::class, 'index'])->name('search.index');
Route::get('/api/search', [SearchController::class, 'index'])->name('api.search');

Route::get('/e/{slug}', [EntityShowController::class, 'show'])->name('entities.show');
Route::get('/top/{slug}', [TopRankingController::class, 'show'])->name('rankings.show');
Route::get('/api/categories/{slug}/ranking', [CategoryRankingController::class, 'index'])->name('api.categories.ranking');

Route::middleware(['auth', 'throttle:ratings'])->group(function (): void {
    Route::put('/api/entities/{entity}/rating', [RatingController::class, 'update'])->name('api.entities.rating.update');
    Route::delete('/api/entities/{entity}/rating', [RatingController::class, 'destroy'])->name('api.entities.rating.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
