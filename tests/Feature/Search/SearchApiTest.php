<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;

test('GET /api/search returns json response with data and meta', function () {
    $category = Category::factory()->create(['name' => 'ISP', 'slug' => 'isp']);
    $entity = Entity::factory()->create([
        'name' => 'IndiHome Fiber',
        'category_id' => $category->id,
    ]);

    $response = $this->getJson('/api/search?q=indihome');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                    'type_label',
                    'description',
                    'category' => ['id', 'name', 'slug'],
                    'parent',
                    'url',
                    'score',
                    'opinion_count',
                    'rating',
                    'rating_count',
                    'priority_tier',
                    'priority_rank',
                    'match_detail',
                ],
            ],
            'meta' => [
                'query',
                'normalized_query',
                'total',
            ],
        ]);

    expect($response->json('data.0.id'))->toBe($entity->id)
        ->and($response->json('meta.total'))->toBe(1);
});

test('GET /api/search with empty query returns empty data', function () {
    $response = $this->getJson('/api/search?q=');

    $response->assertOk()
        ->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
            ],
        ]);
});

test('GET /api/search excludes disabled and unsearchable entities', function () {
    $category = Category::factory()->create();

    $active = Entity::factory()->create([
        'name' => 'Telkomsel Active',
        'category_id' => $category->id,
        'status' => 'active',
        'searchable' => true,
    ]);

    $disabled = Entity::factory()->disabled()->create([
        'name' => 'Telkomsel Disabled',
        'category_id' => $category->id,
    ]);

    $unsearchable = Entity::factory()->create([
        'name' => 'Telkomsel Unsearchable',
        'category_id' => $category->id,
        'status' => 'active',
        'searchable' => false,
    ]);

    $response = $this->getJson('/api/search?q=telkomsel');

    $response->assertOk();
    $ids = array_column($response->json('data'), 'id');

    expect($ids)
        ->toContain($active->id)
        ->not->toContain($disabled->id)
        ->not->toContain($unsearchable->id);
});

test('GET /api/search returns the real Sentimen Netijen score and opinion count when eligible', function () {
    $category = Category::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Samsung Test', 'category_id' => $category->id]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70.0,
    ]);

    $response = $this->getJson('/api/search?q=samsung+test');

    $response->assertOk();
    expect((float) $response->json('data.0.score'))->toBe(70.0)
        ->and($response->json('data.0.opinion_count'))->toBe(100);
});

test('GET /api/search hides the score but still reports opinion_count below the public threshold', function () {
    $category = Category::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Belowthreshold Test', 'category_id' => $category->id]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear,
        'positive_count' => 5,
        'neutral_count' => 2,
        'negative_count' => 3,
        'opinion_count' => 10,
        'score' => 65.0,
    ]);

    $response = $this->getJson('/api/search?q=belowthreshold');

    $response->assertOk();
    expect($response->json('data.0.score'))->toBeNull()
        ->and($response->json('data.0.opinion_count'))->toBe(10);
});

test('GET /api/search falls back to the all-time snapshot when the 365d snapshot does not exist', function () {
    $category = Category::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Alltimefallback Test', 'category_id' => $category->id]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entity->id,
        'period' => Period::All,
        'positive_count' => 40,
        'neutral_count' => 10,
        'negative_count' => 0,
        'opinion_count' => 50,
        'score' => 90.0,
    ]);

    $response = $this->getJson('/api/search?q=alltimefallback');

    $response->assertOk();
    expect((float) $response->json('data.0.score'))->toBe(90.0)
        ->and($response->json('data.0.opinion_count'))->toBe(50);
});

test('GET /api/search returns the first-party rating and rating count', function () {
    $category = Category::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Ratedbrand Test', 'category_id' => $category->id]);
    RatingSnapshot::factory()->create([
        'entity_id' => $entity->id,
        'rating_average' => 4.5,
        'rating_count' => 12,
    ]);

    $response = $this->getJson('/api/search?q=ratedbrand');

    $response->assertOk();
    expect($response->json('data.0.rating'))->toBe(4.5)
        ->and($response->json('data.0.rating_count'))->toBe(12);
});

test('GET /api/search respects limit parameter', function () {
    $category = Category::factory()->create();
    Entity::factory()->count(15)->sequence(fn ($sq) => ['name' => "Product Brand Test {$sq->index}"])->create([
        'category_id' => $category->id,
    ]);

    $response = $this->getJson('/api/search?q=product+brand&limit=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
});
