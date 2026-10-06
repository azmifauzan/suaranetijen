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
