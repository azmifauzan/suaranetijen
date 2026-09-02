<?php

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Search\Services\SearchService;

test('priority 1: exact entity name ranks first', function () {
    $category = Category::factory()->create(['name' => 'Hosting', 'slug' => 'hosting']);

    // Target exact name
    $exact = Entity::factory()->create([
        'name' => 'Niagahoster',
        'category_id' => $category->id,
    ]);

    // Prefix match
    $prefix = Entity::factory()->create([
        'name' => 'Niagahoster Cloud',
        'category_id' => $category->id,
    ]);

    $service = app(SearchService::class);
    $results = $service->search('Niagahoster');

    expect($results['data'][0]['id'])->toBe($exact->id)
        ->and($results['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_EXACT_NAME);
});

test('priority 2: exact alias match ranks above prefix match', function () {
    $category = Category::factory()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);

    // Entity with exact alias match
    $entityWithAlias = Entity::factory()->create([
        'name' => 'Samsung Galaxy S24',
        'category_id' => $category->id,
    ]);
    EntityAlias::factory()->create([
        'entity_id' => $entityWithAlias->id,
        'alias' => 'S24',
        'normalized_alias' => 's24',
        'alias_type' => AliasType::Primary,
    ]);

    // Entity whose name only starts with the prefix "s24"
    Entity::factory()->create([
        'name' => 'S2400 Super Computer',
        'category_id' => $category->id,
    ]);

    $service = app(SearchService::class);
    $results = $service->search('s24');

    expect($results['data'][0]['id'])->toBe($entityWithAlias->id)
        ->and($results['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_EXACT_ALIAS);
});

test('priority 3: prefix match ranks above trigram fuzzy match', function () {
    $category = Category::factory()->create(['name' => 'Automotive', 'slug' => 'automotive']);

    // Prefix match
    $prefixEntity = Entity::factory()->create([
        'name' => 'Toyota Avanza Veloz',
        'category_id' => $category->id,
    ]);

    // Fuzzy/trigram match with typo
    $fuzzyEntity = Entity::factory()->create([
        'name' => 'Toyota Agya',
        'category_id' => $category->id,
    ]);

    $service = app(SearchService::class);
    $results = $service->search('toyota avanza');

    expect($results['data'][0]['id'])->toBe($prefixEntity->id)
        ->and($results['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_PREFIX);
});

test('priority 5: category filter scopes results to selected category', function () {
    $catHosting = Category::factory()->create(['name' => 'Cloud & Hosting', 'slug' => 'cloud-hosting']);
    $catTelco = Category::factory()->create(['name' => 'ISP & Telco', 'slug' => 'isp-telco']);

    $hostingEntity = Entity::factory()->create([
        'name' => 'Biznet Gio Cloud',
        'category_id' => $catHosting->id,
    ]);

    $telcoEntity = Entity::factory()->create([
        'name' => 'Biznet Fiber',
        'category_id' => $catTelco->id,
    ]);

    $service = app(SearchService::class);
    $results = $service->search('biznet', category: 'cloud-hosting');

    $ids = array_column($results['data'], 'id');
    expect($ids)
        ->toContain($hostingEntity->id)
        ->not->toContain($telcoEntity->id);
});
