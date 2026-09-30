<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use Carbon\CarbonImmutable;

// ──────────────────────────────────────────
// GET /top — global ranking page
// ──────────────────────────────────────────

test('GET /top returns 200 with Inertia component Top/Index', function () {
    $response = $this->get('/top');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Top/Index'));
});

test('GET /top passes required props', function () {
    $response = $this->get('/top');

    $response->assertInertia(fn ($page) => $page
        ->component('Top/Index')
        ->has('period')
        ->has('rankings')
        ->has('categories')
        ->has('sponsorTeaser')
    );
});

test('GET /top with period query param uses that period', function () {
    $response = $this->get('/top?period=30d');

    $response->assertInertia(fn ($page) => $page
        ->component('Top/Index')
        ->where('period', '30d')
    );
});

test('GET /top with invalid period defaults to 365d', function () {
    $response = $this->get('/top?period=invalid');

    $response->assertInertia(fn ($page) => $page
        ->component('Top/Index')
        ->where('period', '365d')
    );
});

test('GET /top rankings include category data on each row', function () {
    $category = Category::factory()->create(['status' => 'active']);
    $entity = Entity::factory()->create([
        'status' => EntityStatus::Active,
        'rankable' => true,
        'category_id' => $category->id,
    ]);

    SentimentSnapshot::factory()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 75.0,
        'opinion_count' => 150,
        'positive_count' => 100,
        'neutral_count' => 30,
        'negative_count' => 20,
    ]);

    $response = $this->get('/top');

    $response->assertInertia(fn ($page) => $page
        ->component('Top/Index')
        ->has('rankings', fn ($rankings) => $rankings
            ->where('0.category.slug', $category->slug)
        )
    );
});

test('GET /top sponsorTeaser has expected shape', function () {
    $response = $this->get('/top');

    $response->assertInertia(fn ($page) => $page
        ->has('sponsorTeaser', fn ($teaser) => $teaser
            ->has('is_empty')
            ->has('top_entries')
            ->etc()
        )
    );
});

// ──────────────────────────────────────────
// GET /top/{slug} — per-category page now includes sponsorTeaser
// ──────────────────────────────────────────

test('GET /top/{slug} passes sponsorTeaser prop', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $response = $this->get("/top/{$category->slug}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Top/Show')
        ->has('sponsorTeaser')
    );
});

test('GET /top/{slug} sponsorTeaser is empty when no sponsors in that category', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $response = $this->get("/top/{$category->slug}");

    $response->assertInertia(fn ($page) => $page
        ->component('Top/Show')
        ->has('sponsorTeaser', fn ($teaser) => $teaser
            ->where('is_empty', true)
            ->where('entries', [])
        )
    );
});

// ──────────────────────────────────────────
// SponsorLeaderboardService::getCategoryTeaser
// ──────────────────────────────────────────

test('getCategoryTeaser returns only sponsors from the given category', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();

    $catA = Category::factory()->create(['status' => 'active']);
    $catB = Category::factory()->create(['status' => 'active']);

    $entityInA = Entity::factory()->create([
        'status' => EntityStatus::Active,
        'searchable' => true,
        'category_id' => $catA->id,
    ]);
    $entityInB = Entity::factory()->create([
        'status' => EntityStatus::Active,
        'searchable' => true,
        'category_id' => $catB->id,
    ]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityInA->id,
        'settled_total_amount' => 200000,
        'first_settled_at' => CarbonImmutable::now(),
        'status' => SponsoredEntryStatus::Active,
    ]);
    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityInB->id,
        'settled_total_amount' => 500000,
        'first_settled_at' => CarbonImmutable::now(),
        'status' => SponsoredEntryStatus::Active,
    ]);

    $result = $service->getCategoryTeaser($catA->id, 3);

    expect($result['is_empty'])->toBeFalse();
    expect($result['entries'])->toHaveCount(1);
    expect($result['entries'][0]['entity_id'])->toBe($entityInA->id);
});

test('getCategoryTeaser returns at most the requested limit', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $cat = Category::factory()->create(['status' => 'active']);

    foreach (range(1, 5) as $i) {
        $e = Entity::factory()->create([
            'status' => EntityStatus::Active,
            'searchable' => true,
            'category_id' => $cat->id,
        ]);
        SponsoredEntry::factory()->create([
            'period_id' => $period->id,
            'entity_id' => $e->id,
            'settled_total_amount' => $i * 10000,
            'first_settled_at' => CarbonImmutable::now(),
            'status' => SponsoredEntryStatus::Active,
        ]);
    }

    $result = $service->getCategoryTeaser($cat->id, 3);

    expect($result['entries'])->toHaveCount(3);
});

test('getCategoryTeaser returns is_empty true when category has no sponsors', function () {
    $service = app(SponsorLeaderboardService::class);
    $cat = Category::factory()->create(['status' => 'active']);

    $result = $service->getCategoryTeaser($cat->id, 3);

    expect($result['is_empty'])->toBeTrue();
    expect($result['entries'])->toBeEmpty();
});

test('getCategoryTeaser excludes zero-amount entries', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $cat = Category::factory()->create(['status' => 'active']);

    $e = Entity::factory()->create([
        'status' => EntityStatus::Active,
        'searchable' => true,
        'category_id' => $cat->id,
    ]);
    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $e->id,
        'settled_total_amount' => 0,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $result = $service->getCategoryTeaser($cat->id, 3);

    expect($result['is_empty'])->toBeTrue();
});

test('getCategoryTeaser falls back to all-time when active period has no category entries', function () {
    $service = app(SponsorLeaderboardService::class);
    $cat = Category::factory()->create(['status' => 'active']);

    $e = Entity::factory()->create([
        'status' => EntityStatus::Active,
        'searchable' => true,
        'category_id' => $cat->id,
    ]);

    // Create entry in a *past* (non-active) period only
    $pastPeriod = SponsorPeriod::factory()->create([
        'status' => SponsorPeriodStatus::Closed,
        'starts_at' => CarbonImmutable::now()->subWeeks(2),
        'ends_at' => CarbonImmutable::now()->subWeeks(1),
    ]);
    SponsoredEntry::factory()->create([
        'period_id' => $pastPeriod->id,
        'entity_id' => $e->id,
        'settled_total_amount' => 150000,
        'first_settled_at' => CarbonImmutable::now()->subWeeks(2),
        'status' => SponsoredEntryStatus::Active,
    ]);

    $result = $service->getCategoryTeaser($cat->id, 3);

    // Falls back to all-time: should find the past-period entry
    expect($result['is_empty'])->toBeFalse();
    expect($result['entries'])->toHaveCount(1);
    expect($result['entries'][0]['entity_id'])->toBe($e->id);
});

test('ranking of a root category includes entities from its child categories', function () {
    $root = Category::factory()->create(['slug' => 'induk-otomotif', 'parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $entity = Entity::factory()->create(['category_id' => $child->id, 'status' => EntityStatus::Active, 'rankable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 500]);

    $this->get('/top/induk-otomotif')->assertInertia(fn ($page) => $page
        ->component('Top/Show')
        ->has('rankings', 1)
    );
});
