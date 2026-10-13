<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Inertia\Testing\AssertableInertia;

test('category page blocks leave out entities below the public threshold', function () {
    $category = Category::factory()->create(['slug' => 'kategori-campur']);
    $eligible = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $thin = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $eligible->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 40]);
    SentimentSnapshot::factory()->create(['entity_id' => $thin->id, 'period' => Period::OneYear->value, 'score' => 60.0, 'opinion_count' => 5]);

    $this->get('/category/kategori-campur')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('mostDiscussed', 1)
        ->where('mostDiscussed.0.slug', $eligible->slug)
        ->has('recentlyUpdated', 1)
        ->where('recentlyUpdated.0.slug', $eligible->slug)
    );
});

test('category top list falling back to all-time skips entities thin over 365d', function () {
    $category = Category::factory()->create(['slug' => 'kategori-lama']);
    $stale = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true]);
    $allTimeOnly = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $stale->id, 'period' => Period::All->value, 'score' => 90.0, 'opinion_count' => 80]);
    SentimentSnapshot::factory()->create(['entity_id' => $stale->id, 'period' => Period::OneYear->value, 'score' => 90.0, 'opinion_count' => 5]);
    SentimentSnapshot::factory()->create(['entity_id' => $allTimeOnly->id, 'period' => Period::All->value, 'score' => 70.0, 'opinion_count' => 80]);

    $this->get('/category/kategori-lama')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('topSentimen', 1)
        ->where('topSentimen.0.slug', $allTimeOnly->slug)
        ->where('topSentimen.0.rank', 1)
    );
});

test('a top page without a ranking is noindex and left out of the sitemap', function () {
    Category::factory()->create(['slug' => 'tanpa-ranking']);

    expect($this->get('/top/tanpa-ranking')->getContent())->toContain('name="robots" content="noindex, follow"')
        ->and($this->get('/sitemap.xml')->getContent())->not->toContain('/top/tanpa-ranking');
});

test('a top page with a ranking stays indexable and in the sitemap', function () {
    $category = Category::factory()->create(['slug' => 'ada-ranking']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 500]);

    expect($this->get('/top/ada-ranking')->getContent())->toContain('name="robots" content="index, follow"')
        ->and($this->get('/sitemap.xml')->getContent())->toContain('/top/ada-ranking');
});
