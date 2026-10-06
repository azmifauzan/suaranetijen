<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Http\Controllers\SitemapController;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo']]);
    SitemapController::clearCache();
});

function comparisonEntity(string $slug, string $name, int $opinions, float $score, string $category = 'smartphone'): Entity
{
    $cat = Category::query()->firstOrCreate(['slug' => $category], ['name' => ucfirst($category), 'status' => 'active']);
    $entity = Entity::query()->create([
        'name' => $name,
        'slug' => $slug,
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $cat->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => $opinions >= 30 ? $score : null,
        'opinion_count' => $opinions,
        'positive_count' => (int) ($opinions * 0.7),
        'neutral_count' => (int) ($opinions * 0.1),
        'negative_count' => $opinions - (int) ($opinions * 0.7) - (int) ($opinions * 0.1),
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    return $entity;
}

test('a curated pair renders both sides side by side and is indexable', function () {
    comparisonEntity('oppo', 'Oppo', 200, 70.2);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);

    $response = $this->get('/banding/oppo-vs-vivo');

    $response->assertOk()->assertHeaderMissing('X-Robots-Tag');
    expect($response->getContent())->toContain('name="robots" content="index, follow"');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Comparison/Show')
        ->where('indexable', true)
        ->has('comparison.sides', 2)
        ->where('comparison.sides.0.slug', 'oppo')
        ->where('comparison.sides.1.slug', 'vivo')
        ->where('comparison.verdict', 'Sentimen netizen untuk Vivo (89/100) lebih tinggi daripada Oppo (70/100).')
        ->has('comparisonSeo.faq', 3)
        ->has('seo.site_url')
    );
});

test('scores within five points are described as comparable, never as a winner', function () {
    comparisonEntity('oppo', 'Oppo', 200, 80.0);
    comparisonEntity('vivo', 'Vivo', 150, 83.0);

    $this->get('/banding/oppo-vs-vivo')->assertInertia(fn (Assert $page) => $page
        ->where('comparison.verdict', 'Sentimen netizen untuk Oppo (80/100) dan Vivo (83/100) relatif setara.'));
});

test('the reversed pair redirects permanently to the canonical order', function () {
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);

    $this->get('/banding/vivo-vs-oppo')->assertRedirect('/banding/oppo-vs-vivo')->assertStatus(301);
});

test('a pair outside the curated list renders but is noindex', function () {
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('realme', 'Realme', 120, 83.7);

    $response = $this->get('/banding/oppo-vs-realme');

    $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    $response->assertInertia(fn (Assert $page) => $page->where('indexable', false));
});

test('entities from different categories are noindex even when listed', function () {
    config(['comparisons.pairs' => ['mobil-x-vs-oppo']]);
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('mobil-x', 'Mobil X', 120, 70.0, 'mobil');

    $this->get('/banding/mobil-x-vs-oppo')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
});

test('thin, missing, malformed and identical pairs are 404', function () {
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('kecil', 'Kecil', 10, 0);

    $this->get('/banding/kecil-vs-oppo')->assertNotFound();
    $this->get('/banding/oppo-vs-tidak-ada')->assertNotFound();
    $this->get('/banding/oppo-vs-oppo')->assertNotFound();
    $this->get('/banding/oppo')->assertNotFound();
});

test('the sitemap lists only curated pairs whose entities are both eligible', function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo', 'oppo-vs-realme', 'infinix-vs-oppo']]);
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);
    comparisonEntity('realme', 'Realme', 12, 0);

    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->toContain('/banding/oppo-vs-vivo</loc>')
        ->and($content)->not->toContain('/banding/oppo-vs-realme')
        ->and($content)->not->toContain('/banding/infinix-vs-oppo');
});

test('an entity page links to its curated comparisons with an eligible other side', function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo', 'oppo-vs-realme']]);
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);
    comparisonEntity('realme', 'Realme', 12, 0);

    $this->get('/e/oppo')->assertInertia(fn (Assert $page) => $page
        ->has('comparisons', 1)
        ->where('comparisons.0.pair', 'oppo-vs-vivo')
        ->where('comparisons.0.label', 'Oppo vs Vivo'));
});

test('the comparison list groups the indexable pairs by category and leaves out pairs a side of which is thin', function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo', 'oppo-vs-realme', 'toyota-vs-daihatsu']]);
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);
    comparisonEntity('realme', 'Realme', 12, 0);
    comparisonEntity('toyota', 'Toyota', 300, 72.0, 'mobil');
    comparisonEntity('daihatsu', 'Daihatsu', 120, 76.0, 'mobil');

    $this->get('/banding')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertInertia(fn (Assert $page) => $page
        ->component('Comparison/Index')
        ->where('total', 2)
        ->has('groups', 2)
        ->where('groups.0.category', 'Mobil')
        ->where('groups.0.pairs.0.pair', 'toyota-vs-daihatsu')
        ->where('groups.1.category', 'Smartphone')
        ->where('groups.1.pairs.0.label', 'Oppo vs Vivo')
        ->where('groups.1.pairs.0.sides.0.score', 84.6)
        ->missing('groups.1.pairs.1'));
});

test('an empty comparison list is noindex and stays out of the sitemap', function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo']]);

    $this->get('/banding')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('/banding</loc>');
});

test('the sitemap lists the comparison list page once there is a pair to list', function () {
    config(['comparisons.pairs' => ['oppo-vs-vivo']]);
    comparisonEntity('oppo', 'Oppo', 200, 84.6);
    comparisonEntity('vivo', 'Vivo', 150, 89.1);

    expect($this->get('/sitemap.xml')->getContent())->toContain('/banding</loc>')->toContain('/banding/oppo-vs-vivo</loc>');
});

test('the public footer links to the comparison list and the list page is served from the page cache', function () {
    $this->get('/banding')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/banding')->assertHeader('X-Page-Cache', 'HIT');

    expect(file_get_contents(resource_path('js/layouts/PublicLayout.vue')))->toContain('comparisonsIndex()');
});
