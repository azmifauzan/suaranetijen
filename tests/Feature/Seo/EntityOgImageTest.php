<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => File::deleteDirectory(storage_path('app/og')));
afterEach(fn () => File::deleteDirectory(storage_path('app/og')));

function ogEntity(string $slug, int $opinions, string $name = 'Infinix'): Entity
{
    $category = Category::query()->firstOrCreate(['slug' => 'smartphone'], ['name' => 'Smartphone', 'status' => 'active']);
    $entity = Entity::query()->create([
        'name' => $name,
        'slug' => $slug,
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => $opinions >= 30 ? 76.3 : null,
        'opinion_count' => $opinions,
        'positive_count' => (int) ($opinions * 0.7),
        'neutral_count' => (int) ($opinions * 0.05),
        'negative_count' => $opinions - (int) ($opinions * 0.7) - (int) ($opinions * 0.05),
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    return $entity;
}

test('an eligible entity gets a 1200x630 png share card', function () {
    ogEntity('infinix', 224);

    $response = $this->get('/og/e/infinix.png');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=86400');

    $size = getimagesize($response->getFile()->getPathname());
    expect($size[0])->toBe(1200)->and($size[1])->toBe(630)->and($size['mime'])->toBe('image/png');
});

test('a long entity name is wrapped and clipped instead of overflowing', function () {
    ogEntity('long-name', 80, 'Nama Produk Yang Sangat Panjang Sekali Dengan Banyak Kata Tambahan Supaya Terpotong');

    $this->get('/og/e/long-name.png')->assertOk();
});

test('the card is cached per version and a new snapshot replaces the old file', function () {
    $entity = ogEntity('infinix', 224);

    $this->get('/og/e/infinix.png')->assertOk();
    $first = glob(storage_path('app/og/infinix-*.png'));
    expect($first)->toHaveCount(1);

    $this->get('/og/e/infinix.png')->assertOk();
    expect(glob(storage_path('app/og/infinix-*.png')))->toBe($first);

    SentimentSnapshot::query()->where('entity_id', $entity->id)->update(['opinion_count' => 300, 'positive_count' => 250]);
    $this->get('/og/e/infinix.png')->assertOk();

    $second = glob(storage_path('app/og/infinix-*.png'));
    expect($second)->toHaveCount(1)->and($second)->not->toBe($first);
});

test('entities under the public threshold and unknown slugs have no share card', function () {
    ogEntity('kecil', 10, 'Kecil');

    $this->get('/og/e/kecil.png')->assertNotFound();
    $this->get('/og/e/tidak-ada.png')->assertNotFound();
});

test('the entity page points og:image at the share card only when one exists', function () {
    ogEntity('infinix', 224);
    ogEntity('kecil', 10, 'Kecil');

    $this->get('/e/infinix')->assertInertia(fn (Assert $page) => $page
        ->where('ogImage', fn ($url) => str_contains($url, '/og/e/infinix.png?v=')));

    $this->get('/e/kecil')->assertInertia(fn (Assert $page) => $page->where('ogImage', null));
});
