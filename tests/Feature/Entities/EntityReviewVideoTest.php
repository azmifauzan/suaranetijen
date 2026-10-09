<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityReviewVideo;
use App\Domains\Entities\Services\EntityReviewVideoFinder;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['sources.youtube.api_key' => 'test-key', 'sources.youtube.api_url' => 'https://yt.test/v3']);
    Cache::flush();
});

function youtubeSearchResult(array $titles): array
{
    return ['items' => collect($titles)->values()->map(fn (string $title, int $i): array => [
        'id' => ['videoId' => 'vid'.str_pad((string) $i, 8, '0')],
        'snippet' => ['title' => $title, 'publishedAt' => '2026-09-01T10:00:00Z'],
    ])->all()];
}

it('accepts a title that reviews this exact model and rejects everything else', function (string $name, string $title, bool $accepted) {
    expect(app(EntityReviewVideoFinder::class)->isAboutEntity($name, $title))->toBe($accepted);
})->with([
    'exact model review' => ['Dreame X60 Ultra', 'Dreame X60 Ultra Review Jujur, Layak Beli?', true],
    'model code in a different spelling' => ['Cuckoo CR-1020F', 'Review Rice Cooker Cuckoo CR 1020F 1.8 liter', true],
    'a different model' => ['Dreame X60 Ultra', 'Review Dreame L10s Ultra', false],
    'a variant that is not this entity' => ['Samsung Galaxy S24', 'Review Samsung Galaxy S24 Ultra Indonesia', false],
    'the variant the entity itself is' => ['Samsung Galaxy S24 Ultra', 'Review Samsung Galaxy S24 Ultra Indonesia', true],
    'not a review' => ['Dreame X60 Ultra', 'Dreame X60 Ultra diskon 12.12 di toko resmi', false],
    'name without a model code needs every word' => ['Samsung Bespoke', 'Review Kulkas Samsung Bespoke AI', true],
    'name without a model code, a word missing' => ['LG InstaView', 'Review kulkas LG terbaru', false],
]);

it('searches YouTube once and keeps at most three matching, unique videos', function () {
    Http::fake(['yt.test/v3/search*' => Http::response(youtubeSearchResult([
        'Review Dreame X60 Ultra #1',
        'Review Dreame X60 Ultra #2',
        'Review Dreame L10s Ultra',
        'Dreame X60 Ultra hands on',
        'Dreame X60 Ultra review lengkap',
    ]))]);
    $entity = Entity::factory()->product()->create(['name' => 'Dreame X60 Ultra']);

    $videos = app(EntityReviewVideoFinder::class)->find($entity);

    expect($videos)->toHaveCount(3)
        ->and(collect($videos)->pluck('title')->all())->not->toContain('Review Dreame L10s Ultra');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['q'] === 'Dreame X60 Ultra review'
        && $request['videoEmbeddable'] === 'true'
        && $request['regionCode'] === 'ID');
});

it('stores videos for products without any, most discussed first, within the limit', function () {
    Http::fake(['yt.test/v3/search*' => fn ($request) => Http::response(youtubeSearchResult(["Review {$request['q']}"]))]);
    $quiet = Entity::factory()->product()->create(['name' => 'Alpha 100']);
    $busy = Entity::factory()->product()->create(['name' => 'Beta 200']);
    $brand = Entity::factory()->create(['name' => 'Gamma Brand']);
    $has = Entity::factory()->product()->create(['name' => 'Delta 300']);
    EntityReviewVideo::factory()->create(['entity_id' => $has->id]);
    SentimentSnapshotForVideos($busy, 50);
    SentimentSnapshotForVideos($quiet, 2);

    $this->artisan('entities:fetch-review-videos', ['--limit' => 1])->assertSuccessful();

    expect(EntityReviewVideo::where('entity_id', $busy->id)->count())->toBe(1)
        ->and(EntityReviewVideo::where('entity_id', $quiet->id)->count())->toBe(0)
        ->and(EntityReviewVideo::where('entity_id', $brand->id)->count())->toBe(0);
    Http::assertSentCount(1);
});

it('does not search a product again within 30 days when nothing was found', function () {
    Http::fake(['yt.test/v3/search*' => Http::response(['items' => []])]);
    Entity::factory()->product()->create(['name' => 'Alpha 100']);

    $this->artisan('entities:fetch-review-videos')->assertSuccessful();
    $this->artisan('entities:fetch-review-videos')->assertSuccessful();

    Http::assertSentCount(1);
});

it('stops without marking the product checked when YouTube rejects the request', function () {
    Http::fake(['yt.test/v3/search*' => Http::response(['error' => 'quotaExceeded'], 403)]);
    $entity = Entity::factory()->product()->create(['name' => 'Alpha 100']);

    $this->artisan('entities:fetch-review-videos')->assertFailed();

    expect(Cache::has("review-videos:checked:{$entity->id}"))->toBeFalse();
});

it('does nothing without a YouTube API key', function () {
    config(['sources.youtube.api_key' => '']);
    Http::fake();
    Entity::factory()->product()->create(['name' => 'Alpha 100']);

    $this->artisan('entities:fetch-review-videos')->assertSuccessful();

    Http::assertNothingSent();
});

function SentimentSnapshotForVideos(Entity $entity, int $opinions): void
{
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => 'all',
        'positive_count' => $opinions,
        'neutral_count' => 0,
        'negative_count' => 0,
        'opinion_count' => $opinions,
        'score' => 100,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);
}

it('passes review videos to the product page, newest first, and none for other entity types', function () {
    $product = Entity::factory()->product()->create();
    $brand = Entity::factory()->create();
    EntityReviewVideo::factory()->create(['entity_id' => $product->id, 'youtube_id' => 'oldvideo001', 'published_at' => now()->subDays(30)]);
    EntityReviewVideo::factory()->create(['entity_id' => $product->id, 'youtube_id' => 'newvideo001', 'published_at' => now()->subDay()]);
    EntityReviewVideo::factory()->create(['entity_id' => $brand->id]);

    $this->get("/e/{$product->slug}")->assertInertia(fn ($page) => $page
        ->component('Entities/Show')
        ->has('reviewVideos', 2)
        ->where('reviewVideos.0.youtube_id', 'newvideo001')
        ->missing('reviewVideos.0.entity_id'));

    $this->get("/e/{$brand->slug}")->assertInertia(fn ($page) => $page->where('reviewVideos', []));
});
