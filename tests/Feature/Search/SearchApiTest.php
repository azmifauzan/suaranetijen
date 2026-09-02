<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;

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

test('GET /api/search respects limit parameter', function () {
    $category = Category::factory()->create();
    Entity::factory()->count(15)->sequence(fn ($sq) => ['name' => "Product Brand Test {$sq->index}"])->create([
        'category_id' => $category->id,
    ]);

    $response = $this->getJson('/api/search?q=product+brand&limit=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
});
