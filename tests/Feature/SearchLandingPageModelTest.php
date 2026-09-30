<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Themes\Models\Theme;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('it creates a search landing page with default values', function () {
    $category = Category::factory()->create();
    $page = SearchLandingPage::create([
        'keyword' => 'VPS Murah',
        'category_id' => $category->id,
        'source' => SearchLandingPageSource::SearchQuery,
    ]);

    expect($page->slug)->toBe('vps-murah')
        ->and($page->normalized_keyword)->toBe('vps murah')
        ->and($page->status)->toBe(SearchLandingPageStatus::Candidate)
        ->and($page->candidate_signal)->toBe(0)
        ->and($page->category->id)->toBe($category->id);
});

test('it enforces unique normalized_keyword across all statuses', function () {
    SearchLandingPage::factory()->rejected()->create([
        'keyword' => 'Hosting Terbaik',
        'normalized_keyword' => 'hosting terbaik',
        'slug' => 'hosting-terbaik-1',
    ]);

    expect(fn () => SearchLandingPage::factory()->candidate()->create([
        'keyword' => 'Hosting Terbaik',
        'normalized_keyword' => 'hosting terbaik',
        'slug' => 'hosting-terbaik-2',
    ]))->toThrow(QueryException::class);
});

test('it cascades pivot deletion when landing page or theme is deleted', function () {
    $page = SearchLandingPage::factory()->create();
    $theme1 = Theme::create([
        'slug' => 'murah',
        'display_label' => 'Murah',
        'canonical_key' => 'murah',
    ]);
    $theme2 = Theme::create([
        'slug' => 'cepat',
        'display_label' => 'Cepat',
        'canonical_key' => 'cepat',
    ]);

    $page->themes()->attach([$theme1->id, $theme2->id]);

    expect(DB::table('search_landing_page_themes')->count())->toBe(2);

    // Deleting theme cascades
    $theme1->delete();
    expect(DB::table('search_landing_page_themes')->count())->toBe(1)
        ->and(DB::table('search_landing_page_themes')->first()->theme_id)->toBe($theme2->id);

    // Deleting landing page cascades
    $page->delete();
    expect(DB::table('search_landing_page_themes')->count())->toBe(0);
});

test('it filters by status scopes correctly', function () {
    SearchLandingPage::factory()->candidate()->create();
    SearchLandingPage::factory()->draft()->create();
    SearchLandingPage::factory()->published()->create();
    SearchLandingPage::factory()->rejected()->create();

    expect(SearchLandingPage::candidate()->count())->toBe(1)
        ->and(SearchLandingPage::draft()->count())->toBe(1)
        ->and(SearchLandingPage::published()->count())->toBe(1)
        ->and(SearchLandingPage::rejected()->count())->toBe(1);
});
