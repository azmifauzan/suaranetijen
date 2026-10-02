<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntitySeoService;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('auth routes return X-Robots-Tag noindex header and raw HTML has noindex', function () {
    $response = $this->get('/login');
    $response->assertOk();
    $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    expect($response->getContent())->toContain('name="robots" content="noindex, follow"');

    $registerResponse = $this->get('/register');
    $registerResponse->assertOk();
    $registerResponse->assertHeader('X-Robots-Tag', 'noindex, follow');
    expect($registerResponse->getContent())->toContain('name="robots" content="noindex, follow"');
});

test('filtered search returns X-Robots-Tag noindex and unfiltered search does not', function () {
    $filtered = $this->get('/search?q=samsung');
    $filtered->assertOk();
    $filtered->assertHeader('X-Robots-Tag', 'noindex, follow');
    expect($filtered->getContent())->toContain('name="robots" content="noindex, follow"');

    $unfiltered = $this->get('/search');
    $unfiltered->assertOk();
    $unfiltered->assertHeaderMissing('X-Robots-Tag');
    expect($unfiltered->getContent())->toContain('name="robots" content="index, follow"');
});

test('entity below threshold returns X-Robots-Tag noindex and raw HTML has noindex', function () {
    $category = Category::query()->create([
        'name' => 'Smartphone',
        'slug' => 'smartphone',
        'status' => 'active',
    ]);

    $entity = Entity::query()->create([
        'name' => 'Ineligible Brand',
        'slug' => 'ineligible-brand',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => null,
        'opinion_count' => 10,
        'positive_count' => 8,
        'neutral_count' => 1,
        'negative_count' => 1,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $response = $this->get("/e/{$entity->slug}");
    $response->assertOk();
    $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    expect($response->getContent())->toContain('name="robots" content="noindex, follow"');
});

test('entity above threshold returns index follow and rich SEO props', function () {
    $category = Category::query()->create([
        'name' => 'Smartphone',
        'slug' => 'smartphone',
        'status' => 'active',
    ]);

    $entity = Entity::query()->create([
        'name' => 'Samsung S24',
        'slug' => 'samsung-s24',
        'type' => EntityType::Product,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 85.0,
        'opinion_count' => 150,
        'positive_count' => 120,
        'neutral_count' => 15,
        'negative_count' => 15,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $response = $this->get("/e/{$entity->slug}");
    $response->assertOk();
    $response->assertHeaderMissing('X-Robots-Tag');
    expect($response->getContent())->toContain('name="robots" content="index, follow"');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Entities/Show')
        ->where('sentiment.is_eligible', true)
        ->has('seo.site_url')
        ->has('seo.site_name')
        ->has('entitySeo.title')
        ->has('entitySeo.meta_description')
        ->has('entitySeo.intent_subtitle')
        ->has('entitySeo.faq')
        ->has('entitySeo.breadcrumb_json_ld')
        ->has('entitySeo.faq_json_ld')
    );
});

test('sitemap excludes /search and uses cached XML response', function () {
    SitemapController::clearCache();

    $response = $this->get('/sitemap.xml');
    $response->assertOk();
    $content = $response->getContent();

    expect($content)->not->toContain('<loc>'.rtrim(config('app.url'), '/').'/search</loc>');
    expect(Cache::has(SitemapController::CACHE_KEY))->toBeTrue();
});

test('saving SentimentSnapshot clears sitemap cache', function () {
    Cache::put(SitemapController::CACHE_KEY, '<xml>cached</xml>', 3600);
    expect(Cache::has(SitemapController::CACHE_KEY))->toBeTrue();

    $category = Category::query()->create([
        'name' => 'Automotive',
        'slug' => 'automotive',
        'status' => 'active',
    ]);

    $entity = Entity::query()->create([
        'name' => 'Test Mobil',
        'slug' => 'test-mobil',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 80.0,
        'opinion_count' => 50,
        'positive_count' => 40,
        'neutral_count' => 5,
        'negative_count' => 5,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    expect(Cache::has(SitemapController::CACHE_KEY))->toBeFalse();
});

test('category show and top ranking pages pass context_description', function () {
    $category = Category::query()->create([
        'name' => 'Smartphone',
        'slug' => 'smartphone',
        'status' => 'active',
    ]);

    $catResponse = $this->get("/category/{$category->slug}");
    $catResponse->assertOk();
    $catResponse->assertInertia(fn (Assert $page) => $page
        ->component('Category/Show')
        ->has('category.context_description')
    );

    $topResponse = $this->get("/top/{$category->slug}");
    $topResponse->assertOk();
    $topResponse->assertInertia(fn (Assert $page) => $page
        ->component('Top/Show')
        ->has('category.context_description')
    );
});

test('default og image in app blade is png and exists in public', function () {
    $response = $this->get('/');
    $response->assertOk();

    expect($response->getContent())->toContain('/og-image.png');
    expect(file_exists(public_path('og-image.png')))->toBeTrue();
});

test('entity SEO meta description skips superlative theme labels instead of stripping them', function () {
    $category = Category::query()->create(['name' => 'Smartphone', 'slug' => 'smartphone', 'status' => 'active']);
    $entity = Entity::query()->create([
        'name' => 'Samsung',
        'slug' => 'samsung',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    $seo = app(EntitySeoService::class)->generate(
        $entity->load('category'),
        ['is_eligible' => true, 'score' => 72.5, 'opinion_count' => 100, 'distribution' => ['positive_pct' => 70.0, 'neutral_pct' => 5.0, 'negative_pct' => 25.0]],
        ['positive_themes' => [
            ['display_label' => 'Samsung terbaik', 'observation_count' => 7],
            ['display_label' => 'Ponsel lipat bagus', 'observation_count' => 4],
        ], 'negative_themes' => []],
    );

    expect($seo['meta_description'])
        ->toContain('Paling sering dipuji: Ponsel lipat bagus.')
        ->not->toContain('terbaik')
        ->and(mb_strlen($seo['meta_description']))->toBeLessThanOrEqual(160)
        ->and(collect($seo['faq'])->pluck('answer')->implode(' '))->not->toContain('objektif');
});
