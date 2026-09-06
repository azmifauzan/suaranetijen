<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use Inertia\Testing\AssertableInertia as Assert;

test('static trust and legal pages render successfully', function () {
    Source::query()->create([
        'key' => 'diskusiwebhosting',
        'name' => 'DiskusiWebHosting',
        'adapter' => 'App\Domains\Sources\Adapters\DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => true,
        'priority' => 10,
    ]);

    $this->get('/methodology')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pages/Methodology'));
    $this->get('/sources')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pages/Sources'));
    $this->get('/about')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pages/About'));
    $this->get('/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pages/Terms'));
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Pages/Privacy'));
});

test('public document shell exposes Indonesian SEO defaults', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('<html lang="id"', false)
        ->assertSee('<title data-inertia="title">SuaraNetijen — Indeks Sentimen Publik Indonesia</title>', false)
        ->assertSee('name="description"', false)
        ->assertSee('opini netizen', false)
        ->assertSee('name="robots" content="index, follow"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('property="og:locale" content="id_ID"', false);

    expect($response->getContent())
        ->not->toContain('laravel/vue-starter-kit')
        ->not->toContain('laravel.com/docs/starter-kits')
        ->not->toContain('Laravel');

    expect(substr_count($response->getContent(), '<title'))->toBe(1);
});

test('public document shell exposes SuaraNetijen favicon assets', function () {
    $response = $this->get('/');
    $favicon = file_get_contents(public_path('favicon.ico'));
    $appleTouchIcon = imagecreatefrompng(public_path('apple-touch-icon.png'));

    $response
        ->assertSee('href="/favicon.ico?v=2"', false)
        ->assertSee('href="/favicon.svg?v=2"', false)
        ->assertSee('href="/apple-touch-icon.png?v=2"', false);

    expect($appleTouchIcon)->toBeInstanceOf(GdImage::class);

    $applePixel = imagecolorsforindex($appleTouchIcon, imagecolorat($appleTouchIcon, 30, 30));

    expect($applePixel['green'])
        ->toBeGreaterThan($applePixel['red'])
        ->toBeGreaterThan($applePixel['blue']);

    expect($favicon)->toBeString();

    $entryOffset = unpack('V', substr($favicon, 18, 4))[1];
    $entrySize = unpack('V', substr($favicon, 14, 4))[1];
    $faviconImage = imagecreatefromstring(substr($favicon, $entryOffset, $entrySize));

    expect($faviconImage)->toBeInstanceOf(GdImage::class);

    $faviconPixel = imagecolorsforindex($faviconImage, imagecolorat($faviconImage, 10, 10));

    expect($faviconPixel['green'])
        ->toBeGreaterThan($faviconPixel['red'])
        ->toBeGreaterThan($faviconPixel['blue']);
});

test('missing Inertia pages render the branded error component', function () {
    $response = $this->get('/page-that-does-not-exist', [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'ErrorPage')
        ->assertJsonPath('props.status', 404);
});

test('non-Inertia missing pages use the branded HTML fallback', function () {
    $response = $this->get('/another-page-that-does-not-exist');

    $response
        ->assertNotFound()
        ->assertSee('SuaraNetijen')
        ->assertSee('Halaman tidak ditemukan')
        ->assertDontSee('Laravel');
});

test('category overview page renders with top sentiment and discussed lists', function () {
    $category = Category::query()->create([
        'name' => 'Web Hosting',
        'slug' => 'web-hosting',
        'is_active' => true,
    ]);

    $entity = Entity::query()->create([
        'name' => 'Niagahoster',
        'slug' => 'niagahoster',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 85.5,
        'opinion_count' => 45,
        'positive_count' => 35,
        'neutral_count' => 7,
        'negative_count' => 3,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $this->get("/category/{$category->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Category/Show')
            ->has('category')
            ->has('topSentimen', 1)
            ->has('mostDiscussed', 1)
            ->has('recentlyUpdated', 1)
        );
});

test('sitemap indexes only active entities that clear public score threshold and excludes thin entities', function () {
    $category = Category::query()->create([
        'name' => 'Fintech',
        'slug' => 'fintech',
        'is_active' => true,
    ]);

    // Eligible entity (opinion_count >= 30)
    $eligibleEntity = Entity::query()->create([
        'name' => 'Bibit',
        'slug' => 'bibit',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $eligibleEntity->id,
        'period' => Period::OneYear->value,
        'score' => 78.0,
        'opinion_count' => 40,
        'positive_count' => 30,
        'neutral_count' => 4,
        'negative_count' => 6,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // Ineligible entity (< 30 opinions, below threshold, thin page per docs/13, docs/17)
    $ineligibleEntity = Entity::query()->create([
        'name' => 'Ajaib New',
        'slug' => 'ajaib-new',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $ineligibleEntity->id,
        'period' => Period::OneYear->value,
        'score' => null,
        'opinion_count' => 12,
        'positive_count' => 10,
        'neutral_count' => 1,
        'negative_count' => 1,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // Entity that only clears threshold on a 30d snapshot, with no 365d/all snapshot yet —
    // must stay out of the sitemap since public eligibility is judged on 365d/all (docs/11, docs/13).
    $shortWindowOnlyEntity = Entity::query()->create([
        'name' => 'Flip Baru',
        'slug' => 'flip-baru',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $shortWindowOnlyEntity->id,
        'period' => Period::ThirtyDays->value,
        'score' => 90.0,
        'opinion_count' => 35,
        'positive_count' => 32,
        'neutral_count' => 2,
        'negative_count' => 1,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // The page prefers an existing 365-day snapshot even when the all-time
    // snapshot would clear the threshold, so this entity must stay out too.
    $mismatchedEntity = Entity::query()->create([
        'name' => 'Indeks Tidak Selaras',
        'slug' => 'indeks-tidak-selaras',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $mismatchedEntity->id,
        'period' => Period::OneYear->value,
        'score' => null,
        'opinion_count' => 12,
        'positive_count' => 8,
        'neutral_count' => 2,
        'negative_count' => 2,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $mismatchedEntity->id,
        'period' => Period::All->value,
        'score' => 80.0,
        'opinion_count' => 45,
        'positive_count' => 36,
        'neutral_count' => 5,
        'negative_count' => 4,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

    $content = $response->getContent();

    // Must include eligible entity
    expect($content)->toContain('/e/bibit');

    // MUST NOT include ineligible / thin entity
    expect($content)->not->toContain('/e/ajaib-new');

    // MUST NOT include an entity eligible only on a non-public-default period
    expect($content)->not->toContain('/e/flip-baru');
    expect($content)->not->toContain('/e/indeks-tidak-selaras');

    // Must include category & top ranking
    expect($content)->toContain('/category/fintech');
    expect($content)->toContain('/top/fintech');

    // Must include static trust pages
    expect($content)->toContain('/methodology');
    expect($content)->toContain('/sources');
    expect($content)->toContain('/about');
    expect($content)->toContain('/terms');
    expect($content)->toContain('/privacy');
});
