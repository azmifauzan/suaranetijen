<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Cache::flush());

function cacheEntity(string $slug, int $opinions): Entity
{
    $category = Category::query()->firstOrCreate(['slug' => 'smartphone'], ['name' => 'Smartphone', 'status' => 'active']);
    $entity = Entity::query()->create([
        'name' => ucfirst($slug),
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
        'score' => $opinions >= 30 ? 75.0 : null,
        'opinion_count' => $opinions,
        'positive_count' => (int) ($opinions * 0.7),
        'neutral_count' => 0,
        'negative_count' => $opinions - (int) ($opinions * 0.7),
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    return $entity;
}

it('serves the second cookie-less request from the cache without a cookie or a query', function () {
    $first = $this->get('/methodology');
    $first->assertOk()->assertHeader('X-Page-Cache', 'MISS');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $second = $this->get('/methodology');

    $second->assertOk()->assertHeader('X-Page-Cache', 'HIT')->assertHeaderMissing('Set-Cookie');
    expect($second->getContent())->toBe($first->getContent())->and($queries)->toBe(0);
});

it('never caches or serves a request that carries a cookie', function () {
    $this->get('/methodology')->assertHeader('X-Page-Cache', 'MISS');

    $withCookie = $this->withUnencryptedCookie('appearance', 'dark')->get('/methodology');

    $withCookie->assertOk();
    expect($withCookie->headers->has('X-Page-Cache'))->toBeFalse();
});

it('bypasses the cache for a query string, an Inertia request and a POST', function () {
    $this->get('/methodology')->assertHeader('X-Page-Cache', 'MISS');

    expect($this->get('/methodology?x=1')->headers->has('X-Page-Cache'))->toBeFalse()
        ->and($this->get('/methodology', ['X-Inertia' => 'true'])->headers->has('X-Page-Cache'))->toBeFalse();
});

it('does not cache a page that is not on the list or an error response', function () {
    expect($this->get('/search')->headers->has('X-Page-Cache'))->toBeFalse();

    $this->get('/e/tidak-ada')->assertNotFound();
    expect(Cache::get('page-cache:'.sha1('|localhost/e/tidak-ada')))->toBeNull();
});

it('keeps the noindex header on a cached thin entity page', function () {
    cacheEntity('kecil', 10);

    $this->get('/e/kecil')->assertHeader('X-Robots-Tag', 'noindex, follow')->assertHeader('X-Page-Cache', 'MISS');

    $this->get('/e/kecil')->assertHeader('X-Page-Cache', 'HIT')->assertHeader('X-Robots-Tag', 'noindex, follow');
});

it('does not cache the entity page of a sponsored entity, so its views keep counting', function () {
    $entity = cacheEntity('disponsori', 80);

    $period = SponsorPeriod::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    $entry = SponsoredEntry::factory()->create(['entity_id' => $entity->id, 'period_id' => $period->id]);

    $this->get('/e/disponsori')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/e/disponsori')->assertHeader('X-Page-Cache', 'MISS');

    expect($entry->fresh()->views_count)->toBe(2);
});

it('drops cached pages when an entity or category is edited', function () {
    $entity = cacheEntity('berubah', 80);
    $this->get('/e/berubah')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/e/berubah')->assertHeader('X-Page-Cache', 'HIT');

    $entity->update(['description' => 'Deskripsi baru']);
    $this->get('/e/berubah')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/e/berubah')->assertHeader('X-Page-Cache', 'HIT');

    $entity->category->update(['name' => 'Ponsel']);
    $this->get('/e/berubah')->assertHeader('X-Page-Cache', 'MISS');
});

it('keeps a page cached when only a score changes', function () {
    $entity = cacheEntity('stabil', 80);
    $this->get('/e/stabil')->assertHeader('X-Page-Cache', 'MISS');

    $entity->update(['updated_at' => now()->addMinute()]);

    $this->get('/e/stabil')->assertHeader('X-Page-Cache', 'HIT');
});
