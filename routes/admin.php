<?php

use App\Domains\Admin\Controllers\AdminDashboardController;
use App\Domains\Admin\Controllers\AdminOperationsController;
use App\Domains\Entities\Controllers\AdminCategoryController;
use App\Domains\Entities\Controllers\AdminEntityAliasController;
use App\Domains\Entities\Controllers\AdminEntityController;
use App\Domains\Sources\Controllers\AdminSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Categories management
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/toggle-status', [AdminCategoryController::class, 'toggleStatus'])->name('categories.toggle-status');

    // Entities management
    Route::get('/entities', [AdminEntityController::class, 'index'])->name('entities.index');
    Route::post('/entities', [AdminEntityController::class, 'store'])->name('entities.store');
    Route::get('/entities/{entity}/edit', [AdminEntityController::class, 'edit'])->name('entities.edit');
    Route::put('/entities/{entity}', [AdminEntityController::class, 'update'])->name('entities.update');
    Route::post('/entities/{entity}/toggle-status', [AdminEntityController::class, 'toggleStatus'])->name('entities.toggle-status');

    // Aliases management
    Route::post('/entities/{entity}/aliases', [AdminEntityAliasController::class, 'store'])->name('entities.aliases.store');
    Route::delete('/entities/{entity}/aliases/{alias}', [AdminEntityAliasController::class, 'destroy'])->name('entities.aliases.destroy');

    // Sources management & Kill Switch
    Route::get('/sources', [AdminSourceController::class, 'index'])->name('sources.index');
    Route::post('/sources/{source}/toggle-status', [AdminSourceController::class, 'toggleStatus'])->name('sources.toggle-status');

    // Operations diagnostics & replay
    Route::get('/operations/crawl-states', [AdminOperationsController::class, 'crawlStates'])->name('operations.crawl-states');
    Route::get('/operations/ingestion-failures', [AdminOperationsController::class, 'ingestionFailures'])->name('operations.ingestion-failures');
    Route::get('/operations/unmatched-mentions', [AdminOperationsController::class, 'unmatchedMentions'])->name('operations.unmatched-mentions');
    Route::post('/operations/items/{sourceItem}/replay', [AdminOperationsController::class, 'replayItem'])->name('operations.items.replay');
    Route::post('/operations/failures/{failure}/retry', [AdminOperationsController::class, 'retryFailure'])->name('operations.failures.retry');
});
