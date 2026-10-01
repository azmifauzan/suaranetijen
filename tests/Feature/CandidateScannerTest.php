<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Search\Services\CandidateScannerService;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;

test('multiple queries in single session count as signal 1 and respects threshold', function () {
    config()->set('landing_pages.search_query_min_signals', 2);

    // Session 1: 100 searches for "vps murah"
    foreach (range(1, 10) as $i) {
        SearchQuery::create([
            'query' => 'vps murah',
            'normalized_query' => 'vps murah',
            'result_count' => 5,
            'session_id' => 'session-1',
            'created_at' => now(),
        ]);
    }

    $scanner = new CandidateScannerService;
    $count = $scanner->scanSearchQueries();

    // Signal is only 1 (< min 2), so 0 candidates created
    expect($count)->toBe(0)
        ->and(SearchLandingPage::count())->toBe(0);

    // Session 2: 1 search for "vps murah" -> signal becomes 2
    SearchQuery::create([
        'query' => 'vps murah',
        'normalized_query' => 'vps murah',
        'result_count' => 5,
        'session_id' => 'session-2',
        'created_at' => now(),
    ]);

    $count = $scanner->scanSearchQueries();
    expect($count)->toBe(1);

    $page = SearchLandingPage::first();
    expect($page->keyword)->toBe('vps murah')
        ->and($page->candidate_signal)->toBe(2)
        ->and($page->source)->toBe(SearchLandingPageSource::SearchQuery);
});

test('it skips search queries matching entity name, alias, or category name', function () {
    config()->set('landing_pages.search_query_min_signals', 1);

    $cat = Category::factory()->create(['name' => 'Hosting']);
    $entity = Entity::factory()->create(['name' => 'Niagahoster', 'category_id' => $cat->id, 'status' => EntityStatus::Active]);
    EntityAlias::create(['entity_id' => $entity->id, 'alias' => 'NH Cloud', 'normalized_alias' => 'nh cloud']);

    // Queries matching entity name, alias, category
    SearchQuery::create(['query' => 'Hosting', 'normalized_query' => 'hosting', 'result_count' => 1, 'session_id' => 's1']);
    SearchQuery::create(['query' => 'Niagahoster', 'normalized_query' => 'niagahoster', 'result_count' => 1, 'session_id' => 's2']);
    SearchQuery::create(['query' => 'NH Cloud', 'normalized_query' => 'nh cloud', 'result_count' => 1, 'session_id' => 's3']);

    $scanner = new CandidateScannerService;
    $count = $scanner->scanSearchQueries();

    expect($count)->toBe(0);
});

test('it rejects blocklist words, phone numbers, emails, and urls', function () {
    $scanner = new CandidateScannerService;

    // Blocklist
    expect($scanner->isSafe('slot gacor hari ini'))->toBeFalse()
        ->and($scanner->isSafe('judi online terpercaya'))->toBeFalse();

    // Phone numbers
    expect($scanner->isSafe('hubungi 081234567890 untuk info'))->toBeFalse()
        ->and($scanner->isSafe('+6281234567890 promo'))->toBeFalse();

    // Email
    expect($scanner->isSafe('kontak admin@example.com sekarang'))->toBeFalse();

    // URL
    expect($scanner->isSafe('kunjungi https://spam.com/link'))->toBeFalse();

    // Clean keyword
    expect($scanner->isSafe('vps murah indonesia'))->toBeTrue();
});

test('it excludes Tokoh Publik category from category-theme scan', function () {
    config()->set('landing_pages.category_theme_min_entities', 2);
    config()->set('landing_pages.category_theme_min_observations', 2);

    $tokoh = Category::factory()->create(['name' => 'Tokoh Publik', 'slug' => 'tokoh-publik']);
    $politisi = Category::factory()->create(['name' => 'Politisi', 'slug' => 'politisi', 'parent_id' => $tokoh->id]);

    $theme = Theme::create(['slug' => 'merakyat', 'display_label' => 'Merakyat', 'canonical_key' => 'merakyat']);

    foreach (range(1, 3) as $i) {
        $e = Entity::factory()->create(['category_id' => $politisi->id, 'status' => EntityStatus::Active, 'searchable' => true]);
        EntityThemeSnapshot::create([
            'entity_id' => $e->id,
            'theme_id' => $theme->id,
            'window' => Period::OneYear,
            'observation_count' => 5,
            'positive_count' => 5,
            'neutral_count' => 0,
            'negative_count' => 0,
            'rank' => 1,
            'calculated_at' => now(),
        ]);
    }

    $scanner = new CandidateScannerService;
    $count = $scanner->scanCategoryThemes();

    expect($count)->toBe(0);
});

test('it prevents recreating rejected candidate across statuses', function () {
    config()->set('landing_pages.search_query_min_signals', 1);

    SearchLandingPage::factory()->rejected()->create([
        'keyword' => 'hosting murah',
        'normalized_keyword' => 'hosting murah',
    ]);

    SearchQuery::create([
        'query' => 'hosting murah',
        'normalized_query' => 'hosting murah',
        'result_count' => 5,
        'session_id' => 's1',
        'created_at' => now(),
    ]);

    $scanner = new CandidateScannerService;
    $count = $scanner->scanSearchQueries();

    expect($count)->toBe(0);
});

test('one source throwing exception does not block the other', function () {
    $scanner = new class extends CandidateScannerService
    {
        public function scanSearchQueries(): int
        {
            throw new RuntimeException('Simulated search query failure');
        }

        public function scanCategoryThemes(): int
        {
            return 3;
        }
    };

    $result = $scanner->scan();

    expect($result['search_queries'])->toBe(0)
        ->and($result['category_themes'])->toBe(3)
        ->and(count($result['errors']))->toBe(1)
        ->and($result['errors'][0])->toContain('Simulated search query failure');
});

test('it executes landing-pages:scan-candidates command successfully', function () {
    $this->artisan('landing-pages:scan-candidates', ['--skip-llm' => true])
        ->assertSuccessful();
});

test('it skips queries that mention a public figure name or alias', function () {
    config()->set('landing_pages.search_query_min_signals', 1);

    $person = Entity::factory()->create(['name' => 'Budi Santoso', 'type' => EntityType::Person, 'status' => EntityStatus::Active]);
    EntityAlias::create(['entity_id' => $person->id, 'alias' => 'Pak Budi', 'normalized_alias' => 'pak budi']);

    SearchQuery::create(['query' => 'budi santoso korupsi', 'normalized_query' => 'budi santoso korupsi', 'result_count' => 1, 'session_id' => 's1']);
    SearchQuery::create(['query' => 'pak budi hutang', 'normalized_query' => 'pak budi hutang', 'result_count' => 1, 'session_id' => 's2']);
    SearchQuery::create(['query' => 'vps cepat', 'normalized_query' => 'vps cepat', 'result_count' => 1, 'session_id' => 's3']);

    $count = (new CandidateScannerService)->scanSearchQueries();

    expect($count)->toBe(1)
        ->and(SearchLandingPage::pluck('normalized_keyword')->all())->toBe(['vps cepat']);
});

test('it never turns a complaint theme into a category-theme candidate', function () {
    config()->set('landing_pages.category_theme_min_entities', 2);
    config()->set('landing_pages.category_theme_min_observations', 2);

    $category = Category::factory()->create(['name' => 'Bank']);
    $positive = Theme::create(['slug' => 'cs-cepat', 'display_label' => 'Cs cepat', 'canonical_key' => 'cs-cepat']);
    $complaint = Theme::create(['slug' => 'cs-lambat', 'display_label' => 'Cs lambat merespons', 'canonical_key' => 'cs-lambat']);
    $negated = Theme::create(['slug' => 'tidak-aman', 'display_label' => 'Tidak aman', 'canonical_key' => 'tidak-aman']);

    foreach (range(1, 3) as $i) {
        $e = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
        foreach ([[$positive, 8, 0], [$complaint, 1, 9], [$negated, 6, 0]] as [$theme, $pos, $neg]) {
            EntityThemeSnapshot::create([
                'entity_id' => $e->id,
                'theme_id' => $theme->id,
                'window' => Period::OneYear,
                'observation_count' => $pos + $neg,
                'positive_count' => $pos,
                'neutral_count' => 0,
                'negative_count' => $neg,
                'rank' => 1,
                'calculated_at' => now(),
            ]);
        }
    }

    expect((new CandidateScannerService)->scanCategoryThemes())->toBe(1)
        ->and(SearchLandingPage::pluck('keyword')->all())->toBe(['Bank Cs cepat']);
});
